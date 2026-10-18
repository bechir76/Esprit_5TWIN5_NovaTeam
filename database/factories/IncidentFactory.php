<?php

namespace Database\Factories;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\User;
use Database\Factories\Concerns\UsesTunisiaProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * La référence (INC-2026-0001) est générée par le modèle.
 *
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    use UsesTunisiaProvider;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Commune choisie une fois : l'adresse et les coordonnées restent cohérentes
        $commune = fake()->tunisianCommune();
        [$latitude, $longitude] = fake()->tunisianCoordinates($commune);

        return [
            'type' => fake()->randomElement(IncidentType::cases()),
            // Titre et description réalistes selon le type (fuite, contamination, coupure)
            'title' => fn (array $attributes) => fake()->incidentReport($attributes['type'])[0],
            'description' => fn (array $attributes) => fake()->incidentDescription($attributes['title'], $attributes['type']),
            'severity' => fake()->randomElement(IncidentSeverity::cases()),
            'status' => IncidentStatus::Signale,
            'address' => fake()->tunisianAddress($commune),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'photo_path' => null,
            'infrastructure_id' => Infrastructure::factory(),
            'user_id' => User::factory()->citoyen(),
            'created_at' => fake()->dateTimeBetween('-6 months'),
        ];
    }

    public function reported(): static
    {
        return $this->state(['status' => IncidentStatus::Signale]);
    }

    public function qualified(): static
    {
        return $this->state(['status' => IncidentStatus::Qualifie]);
    }

    public function inProgress(): static
    {
        return $this->state(['status' => IncidentStatus::EnIntervention]);
    }

    /** Résolu : résolu quelques heures à quelques jours après le signalement (jamais dans le futur). */
    public function resolved(): static
    {
        return $this->state([
            'status' => IncidentStatus::Resolu,
            'resolved_at' => fn (array $attributes) => Carbon::parse($attributes['created_at'])
                ->addHours(fake()->numberBetween(2, 240))
                ->min(now()),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(['status' => IncidentStatus::Rejete]);
    }

    public function critical(): static
    {
        return $this->state(['severity' => IncidentSeverity::Critique]);
    }

    public function fuite(): static
    {
        return $this->state(['type' => IncidentType::Fuite]);
    }

    public function contamination(): static
    {
        return $this->state(['type' => IncidentType::Contamination]);
    }

    public function coupure(): static
    {
        return $this->state(['type' => IncidentType::Coupure]);
    }

    public function withoutInfrastructure(): static
    {
        return $this->state(['infrastructure_id' => null]);
    }

    /** Signalé récemment : encore dans le délai cible de sa gravité (au moins une heure de marge). */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => now()->subMinutes(fake()->numberBetween(5, (Incident::make(['severity' => $attributes['severity']])->slaHours() - 1) * 60)),
        ]);
    }

    /** Ouvert, signalé il y a longtemps : délai cible dépassé et alerte déjà envoyée (incidents:check-sla). */
    public function slaBreached(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IncidentStatus::Qualifie,
            'created_at' => now()->subHours(Incident::make(['severity' => $attributes['severity']])->slaHours() + fake()->numberBetween(2, 24)),
            'sla_breached_at' => now()->subHour(),
        ]);
    }

    /**
     * Incident principal avec $count doublons (même type, à moins de 200 m, quelques heures après) :
     * reports_count compte le signalement principal et ses doublons.
     */
    public function withDuplicates(int $count = 2): static
    {
        return $this->state(fn () => ['reports_count' => $count + 1, 'created_at' => now()->subMinutes(fake()->numberBetween(150, 200))])
            ->has(Incident::factory()->count($count)->nearParent(), 'duplicates');
    }

    /** Doublon : copie le type, l'ouvrage et la position (± 100 m) de l'incident principal ($parent). */
    public function nearParent(): static
    {
        return $this->state(fn (array $attributes, ?Incident $parent) => $parent ? [
            'type' => $parent->type,
            'severity' => $parent->severity,
            'status' => IncidentStatus::Signale,
            'infrastructure_id' => $parent->infrastructure_id,
            'address' => $parent->address,
            'latitude' => $parent->latitude + fake()->randomFloat(5, -0.0008, 0.0008),
            'longitude' => $parent->longitude + fake()->randomFloat(5, -0.0008, 0.0008),
            'created_at' => Carbon::parse($parent->created_at)->addMinutes(fake()->numberBetween(10, 120))->min(now()),
        ] : []);
    }

    /**
     * Incident proche d'un point (± 300 m), signalé à une date donnée : sert à former un groupe
     * d'incidents liés (incidents:cluster, 500 m / 6 h).
     */
    public function nearby(float $latitude, float $longitude, Carbon $at): static
    {
        return $this->state(fn () => [
            'status' => IncidentStatus::Signale,
            'latitude' => $latitude + fake()->randomFloat(5, -0.0025, 0.0025),
            'longitude' => $longitude + fake()->randomFloat(5, -0.0025, 0.0025),
            'created_at' => $at->copy()->addMinutes(fake()->numberBetween(0, 150))->min(now()),
        ]);
    }
}
