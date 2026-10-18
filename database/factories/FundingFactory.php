<?php

namespace Database\Factories;

use App\Enums\FundingSource;
use App\Enums\FundingStatus;
use App\Models\Funding;
use App\Models\Project;
use App\Models\User;
use Database\Factories\Concerns\UsesTunisiaProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Par défaut : financement institutionnel encaissé (« confirmé »).
 * La référence (FIN-2026-0001) est générée par le modèle.
 *
 * @extends Factory<Funding>
 */
class FundingFactory extends Factory
{
    use UsesTunisiaProvider;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'source_type' => fake()->randomElement([FundingSource::Municipalite, FundingSource::Association, FundingSource::Entreprise]),
            // Financeur et montant en DT selon la source (« Municipalité de La Soukra », 35 000 DT)
            'donor_name' => fn (array $attributes) => fake()->tunisianDonor($attributes['source_type']),
            'amount' => fn (array $attributes) => fake()->fundingAmount($attributes['source_type']),
            'user_id' => null,
            'funded_at' => fake()->dateTimeBetween('-2 months'),
            'status' => FundingStatus::Confirmed,
            'paid_at' => fn (array $attributes) => $attributes['funded_at'],
            'is_matching' => false,
            'is_anonymous' => false,
            'note' => null,
        ];
    }

    public function municipal(): static
    {
        return $this->state(['source_type' => FundingSource::Municipalite]);
    }

    public function association(): static
    {
        return $this->state(['source_type' => FundingSource::Association]);
    }

    public function company(): static
    {
        return $this->state(['source_type' => FundingSource::Entreprise]);
    }

    /** Don d'un citoyen (petit montant en DT), payé en ligne. */
    public function fromCitizen(?User $user = null): static
    {
        return $this->state(fn () => [
            'source_type' => FundingSource::Citoyen,
            'user_id' => $user ?? User::factory()->citoyen(),
            'donor_name' => $user?->name ?? fake()->tunisianName(),
            'stripe_session_id' => 'cs_test_'.fake()->unique()->bothify('????????????????????????'),
            'stripe_payment_intent_id' => 'pi_test_'.fake()->unique()->bothify('????????????????????????'),
        ]);
    }

    /** Encaissé ; un don citoyen reçoit son numéro de reçu. */
    public function confirmed(): static
    {
        return $this->state(['status' => FundingStatus::Confirmed])
            ->afterCreating(function (Funding $funding): void {
                if ($funding->source_type === FundingSource::Citoyen && ! $funding->receipt_number) {
                    $funding->forceFill(['receipt_number' => Funding::generateReceiptNumber()])->saveQuietly();
                }
            });
    }

    /** En attente du webhook Stripe (pas encore encaissé). */
    public function pending(): static
    {
        return $this->state(['status' => FundingStatus::Pending, 'paid_at' => null, 'stripe_payment_intent_id' => null]);
    }

    /** Remboursé (collecte échouée). */
    public function refunded(): static
    {
        return $this->state(fn () => [
            'status' => FundingStatus::Refunded,
            'stripe_refund_id' => 're_test_'.fake()->unique()->bothify('????????????????????????'),
        ]);
    }

    public function failed(): static
    {
        return $this->state(['status' => FundingStatus::Failed, 'paid_at' => null, 'stripe_payment_intent_id' => null]);
    }

    /** Cofinancement municipal automatique (1 pour 1). */
    public function matching(): static
    {
        return $this->state(fn () => [
            'source_type' => FundingSource::Municipalite,
            'donor_name' => 'Fonds municipal de cofinancement',
            'amount' => fake()->randomElement([20, 50, 100, 200]),
            'is_matching' => true,
            'status' => FundingStatus::Confirmed,
        ]);
    }

    /** Affiché « Anonyme » sur la page Transparence. */
    public function anonymous(): static
    {
        return $this->state(['is_anonymous' => true]);
    }
}
