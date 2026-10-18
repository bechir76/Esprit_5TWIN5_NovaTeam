<?php

namespace App\Services;

use App\Enums\FundingSource;
use App\Enums\FundingStatus;
use App\Enums\ProjectStatus;
use App\Events\FundingConfirmed;
use App\Exceptions\DonationRefused;
use App\Models\Funding;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Throwable;

/**
 * Dons citoyens par Stripe Checkout et remboursements.
 *  - start()        : réserve le montant (don « pending », projet verrouillé) puis crée la Checkout Session ;
 *  - handleEvent()  : webhook signé, seul moyen de confirmer un paiement (idempotent) ;
 *  - closeExpired() : projets échus sans objectif atteint → « échoué » et dons remboursés (relançable).
 * Le montant, le projet et le donateur viennent toujours de la base, jamais du client.
 */
class DonationService
{
    public function __construct(private StripeService $stripe) {}

    /** @return string URL de la page de paiement Stripe */
    public function start(Project $project, User $user, float $amount, bool $anonymous = false): string
    {
        if (! $this->stripe->isConfigured()) {
            throw new DonationRefused('Le paiement en ligne n\'est pas encore configuré. Réessayez plus tard.');
        }

        // Verrou sur le projet : deux dons simultanés ne peuvent pas dépasser l'objectif
        $funding = DB::transaction(function () use ($project, $user, $amount, $anonymous): Funding {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->id);

            if ($refusal = $locked->contributionRefusal()) {
                throw new DonationRefused($refusal);
            }

            $remaining = $locked->remainingAmount(reserved: true);

            if ($amount > $remaining) {
                throw new DonationRefused($remaining < Funding::MIN_CONTRIBUTION
                    ? 'L\'objectif de ce projet est déjà couvert par les dons en cours.'
                    : 'Il ne reste que '.Project::money($remaining).' DT à collecter pour ce projet.');
            }

            return $locked->fundings()->create([
                'source_type' => FundingSource::Citoyen,
                'donor_name' => $user->name,
                'user_id' => $user->id,
                'amount' => $amount,
                'funded_at' => today(),
                'status' => FundingStatus::Pending,
                'is_anonymous' => $anonymous,
            ]);
        });

        try {
            $session = $this->stripe->createCheckoutSession(
                $funding,
                route('projects.donate.success', $project),
                route('projects.donate.cancel', $project),
            );
        } catch (Throwable $e) {
            report($e);
            $funding->update(['status' => FundingStatus::Failed]);

            throw new DonationRefused('Le service de paiement est momentanément indisponible. Votre carte n\'a pas été débitée.');
        }

        $funding->update(['stripe_session_id' => $session['id']]);

        return $session['url'];
    }

    /** Événement webhook déjà vérifié (signature) ; les autres types sont ignorés. */
    public function handleEvent(Event $event): void
    {
        $session = $event->data->object;

        match ($event->type) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => $this->confirmSession($session),
            'checkout.session.expired', 'checkout.session.async_payment_failed' => $this->failSession($session),
            default => null,
        };
    }

    /** pending → confirmed, une seule fois même si Stripe rejoue l'événement. */
    protected function confirmSession(object $session): void
    {
        $funding = $this->fundingForSession($session);

        if (! $funding || ($session->payment_status ?? null) !== 'paid') {
            return;
        }

        // Le montant payé doit correspondre au don enregistré
        [$currency, $expected] = StripeService::chargeAmount((float) $funding->amount);
        if ((int) $session->amount_total !== $expected || strtolower((string) $session->currency) !== $currency) {
            Log::warning('Stripe : montant inattendu pour le don '.$funding->reference, ['session' => $session->id]);

            return;
        }

        $confirmed = DB::transaction(function () use ($funding, $session): ?Funding {
            $locked = Funding::query()->lockForUpdate()->find($funding->id);

            if ($locked->status !== FundingStatus::Pending) {
                return null;
            }

            $locked->markConfirmed(is_string($session->payment_intent ?? null) ? $session->payment_intent : null);

            return $locked;
        });

        if (! $confirmed) {
            return;
        }

        FundingConfirmed::dispatch($confirmed);

        // Paiement arrivé après l'échec de la collecte : remboursé aussitôt
        if ($confirmed->project()->first()?->status === ProjectStatus::Failed) {
            $this->refund($confirmed->fresh());
        }
    }

    /** Session expirée ou paiement refusé : pending → failed (le montant réservé est libéré). */
    protected function failSession(object $session): void
    {
        $funding = $this->fundingForSession($session);

        if ($funding?->status === FundingStatus::Pending) {
            $funding->update(['status' => FundingStatus::Failed]);
        }
    }

    /** Don correspondant à la session : identifiant de session ET metadata funding_id doivent concorder. */
    protected function fundingForSession(object $session): ?Funding
    {
        $funding = Funding::where('stripe_session_id', $session->id ?? null)->first();

        if ($funding && (string) ($session->metadata['funding_id'] ?? '') !== (string) $funding->id) {
            Log::warning('Stripe : metadata funding_id incohérente', ['session' => $session->id]);

            return null;
        }

        return $funding;
    }

    // ---------------------------------------------------------------- Remboursements et clôture

    /**
     * Rembourse un financement encaissé : Stripe pour un don en ligne ; les fonds de cofinancement
     * et les financements saisis par le personnel passent simplement à « remboursé ».
     */
    public function refund(Funding $funding): bool
    {
        if (! $funding->isConfirmed()) {
            return false;
        }

        $refundId = null;

        if ($funding->stripe_payment_intent_id && ! $funding->is_matching) {
            try {
                $refundId = $this->stripe->refund($funding);
            } catch (Throwable $e) {
                report($e);

                return false; // nouvelle tentative au prochain passage de projects:close-expired
            }
        }

        $funding->update(['status' => FundingStatus::Refunded, 'stripe_refund_id' => $refundId]);

        return true;
    }

    /** @return int nombre de financements remboursés */
    public function refundProject(Project $project): int
    {
        return $project->fundings()->confirmed()->get()->filter(fn (Funding $f) => $this->refund($f))->count();
    }

    /**
     * Projets en collecte échus : « financé » si l'objectif est atteint, sinon « échoué » et remboursement.
     * Relançable : les projets déjà échus dont un remboursement avait échoué sont repris.
     *
     * @return array{failed: int, funded: int, refunded: int}
     */
    public function closeExpired(): array
    {
        $stats = ['failed' => 0, 'funded' => 0, 'refunded' => 0];

        $projects = Project::query()
            ->where(fn ($q) => $q->where('status', ProjectStatus::Open->value)->whereDate('end_date', '<', today()))
            ->orWhere(fn ($q) => $q->where('status', ProjectStatus::Failed->value)->whereHas('fundings', fn ($f) => $f->confirmed()))
            ->get();

        foreach ($projects as $project) {
            if ($project->status === ProjectStatus::Open) {
                if ($project->hasReachedTarget()) {
                    $project->transitionTo(ProjectStatus::Funded);
                    $stats['funded']++;

                    continue;
                }

                $project->transitionTo(ProjectStatus::Failed);
                $stats['failed']++;
            }

            $stats['refunded'] += $this->refundProject($project);
        }

        return $stats;
    }
}
