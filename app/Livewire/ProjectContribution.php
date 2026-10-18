<?php

namespace App\Livewire;

use App\Exceptions\DonationRefused;
use App\Http\Requests\StoreContributionRequest;
use App\Models\Funding;
use App\Models\Project;
use App\Services\DonationService;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Carte « Contribuer » de la fiche publique d'un projet : montants rapides ou montant libre, validation en temps réel,
 * puis redirection vers Stripe Checkout (DonationService). Sans JavaScript, le formulaire est envoyé à la route POST.
 * Mêmes règles que la route : StoreContributionRequest, Project::contributionRefusal() et ProjectPolicy::donate.
 */
class ProjectContribution extends Component
{
    public Project $project;

    /** Montant rapide choisi (boutons 20 / 50 / 100 DT). */
    public string $amount = '50';

    /** « Autre montant » : remplace le montant rapide s'il est rempli. */
    public string $amountOther = '';

    /** Don affiché « Anonyme » sur la page Transparence. */
    public bool $anonymous = false;

    /** @var list<int> */
    public array $presets = [20, 50, 100];

    public function mount(Project $project): void
    {
        $this->project = $project;

        // Retour de la route POST (navigateur sans JavaScript) : saisie et erreurs conservées
        $old = (string) old('amount', '50');
        $this->amount = in_array((int) $old, $this->presets, true) ? $old : '50';
        $this->amountOther = (string) old('amount_other', '');
        $this->anonymous = (bool) old('anonymous', false);

        if (session()->has('errors')) {
            $this->setErrorBag(session('errors')->getBag('default'));
        }
    }

    public function updatedAmount(): void
    {
        $this->amountOther = '';
        $this->validateAmount();
    }

    public function updatedAmountOther(): void
    {
        $this->validateAmount();
    }

    public function contribute(DonationService $donations): mixed
    {
        $user = auth()->user();

        if (! $user) {
            return $this->redirectRoute('login');
        }

        // Même règle que la route POST (middleware « verified ») : adresse e-mail vérifiée
        if (! $user->hasVerifiedEmail()) {
            return $this->redirectRoute('verification.notice');
        }

        if ($user->cannot('donate', $this->project)) {
            $this->addError('project', 'Les dons en ligne sont réservés aux comptes citoyens.');

            return null;
        }

        if ($refusal = $this->project->contributionRefusal()) {
            $this->addError('project', $refusal);

            return null;
        }

        $amount = $this->validateAmount();

        try {
            $url = $donations->start($this->project, $user, (float) $amount, $this->anonymous);
        } catch (DonationRefused $e) {
            $this->addError('project', $e->getMessage());

            return null;
        }

        // Page de paiement Stripe (hors du site)
        return $this->redirect($url);
    }

    /** Montant envoyé : « Autre montant » s'il est saisi, sinon le montant rapide. */
    protected function resolvedAmount(): mixed
    {
        return Funding::normalizeAmount(filled($this->amountOther) ? $this->amountOther : $this->amount);
    }

    /** Valide le montant avec les règles de StoreContributionRequest et le renvoie. */
    protected function validateAmount(): mixed
    {
        $this->resetErrorBag('amount');

        return Validator::make(
            ['amount' => $this->resolvedAmount()],
            StoreContributionRequest::amountRules(),
            StoreContributionRequest::amountMessages(),
            ['amount' => 'montant'],
        )->validate()['amount'];
    }

    public function render(): View
    {
        return view('livewire.project-contribution');
    }
}
