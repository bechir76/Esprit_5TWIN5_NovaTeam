<?php

namespace Database\Seeders;

use App\Enums\IncidentStatus;
use App\Enums\InterventionStatus;
use App\Enums\Role;
use App\Models\Incident;
use App\Models\Intervention;
use App\Models\User;
use Database\Factories\InterventionFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 1 à 3 interventions par incident qui n'est plus « signalé », cohérentes avec son statut :
 *  - qualifié        : intervention(s) planifiée(s) ;
 *  - en intervention : une intervention en cours, éventuellement précédée d'un diagnostic terminé ;
 *  - résolu          : interventions terminées avant la date de résolution ;
 *  - rejeté          : un contrôle sur place terminé (aucune anomalie) ou une intervention annulée.
 * Les techniciens assignés sont les techniciens actifs existants.
 */
class InterventionSeeder extends Seeder
{
    /**
     * Interventions du template : référence incident => [[intitulé, équipe, date prévue, durée, statut, compte rendu]].
     */
    protected const TEMPLATE = [
        'INC-2026-0871' => [
            ['Réparation de la conduite DN 600 éclatée', 'Équipe réseau · Tunis', '2026-09-27 08:00', 8, InterventionStatus::Terminee, 'Tronçon de 6 m remplacé, réseau remis en eau à 18:40. Chaussée à reprendre par la municipalité.'],
        ],
        'INC-2026-0884' => [
            ['Réglage du surpresseur de la résidence', 'Équipe réseau · Ben Arous', '2026-09-30 09:00', 2, InterventionStatus::Terminee, 'Consigne du surpresseur corrigée, pression mesurée à 2,8 bar au troisième étage.'],
        ],
        'INC-2026-0899' => [
            ['Remplacement compteur', 'Équipe d\'astreinte', '2026-10-02 09:15', 1, InterventionStatus::Terminee, 'Joint du compteur remplacé, plus aucun suintement constaté.'],
        ],
        'INC-2026-0907' => [
            ['Remplacement vanne d\'isolement V-114', 'Équipe réseau · Tunis', '2026-10-04 08:00', 4, InterventionStatus::EnCours, null],
        ],
        'INC-2026-0912' => [
            ['Diagnostic de la station de pompage', 'Équipe d\'astreinte', '2026-10-07 08:30', 3, InterventionStatus::Planifiee, null],
        ],
        'INC-2026-0918' => [
            ['Recherche de fuite au corrélateur', 'Équipe réseau · Ariana', '2026-10-03 10:00', 2, InterventionStatus::Terminee, 'Fuite localisée sur la conduite DN 300 à hauteur du numéro 22.'],
            ['Réparation conduite DN 300', 'Équipe réseau · Ariana', '2026-10-03 14:30', 6, InterventionStatus::EnCours, null],
        ],
    ];

    public function run(): void
    {
        $technicians = User::where('role', Role::Technicien->value)->where('is_active', true)->get();

        $incidents = Incident::where('status', '!=', IncidentStatus::Signale->value)->orderBy('created_at')->get();

        foreach ($incidents as $incident) {
            if (isset(self::TEMPLATE[$incident->reference])) {
                foreach (self::TEMPLATE[$incident->reference] as [$title, $team, $scheduledAt, $hours, $status, $report]) {
                    $this->create($incident, $technicians, [
                        'title' => $title,
                        'team' => $team,
                        'scheduled_at' => $scheduledAt,
                        'estimated_hours' => $hours,
                        'status' => $status,
                        'report' => $report,
                        'completed_at' => $status === InterventionStatus::Terminee ? Carbon::parse($scheduledAt)->addHours($hours) : null,
                    ]);
                }

                continue;
            }

            foreach ($this->planFor($incident) as $attributes) {
                $this->create($incident, $technicians, $attributes);
            }
        }
    }

    /**
     * Interventions cohérentes avec le statut de l'incident.
     *
     * @return list<array<string, mixed>>
     */
    protected function planFor(Incident $incident): array
    {
        $start = $incident->created_at->copy()->addHours(fake()->numberBetween(2, 24))->setMinutes(fake()->randomElement([0, 30]));

        return match ($incident->status) {
            IncidentStatus::Qualifie => collect(range(1, fake()->numberBetween(1, 2)))
                ->map(fn (int $k) => $this->attributes($incident, InterventionStatus::Planifiee, now()->addDays($k)->setTime(fake()->numberBetween(7, 14), 0)))
                ->all(),

            IncidentStatus::EnIntervention => array_merge(
                fake()->boolean(50) ? [$this->attributes($incident, InterventionStatus::Terminee, $start)] : [],
                [$this->attributes($incident, InterventionStatus::EnCours, $start->copy()->addHours(fake()->numberBetween(4, 24))->min(now()->subHour()))],
                fake()->boolean(30) ? [$this->attributes($incident, InterventionStatus::Planifiee, now()->addDays(2)->setTime(8, 0))] : [],
            ),

            // Toutes les interventions sont terminées avant la résolution
            IncidentStatus::Resolu => collect(range(1, fake()->numberBetween(1, 3)))
                ->map(function (int $k) use ($incident, $start) {
                    $scheduled = $start->copy()->addHours(($k - 1) * 3)->min($incident->resolved_at->copy()->subHours(2));
                    $attributes = $this->attributes($incident, InterventionStatus::Terminee, $scheduled->max($incident->created_at));
                    $attributes['completed_at'] = $incident->resolved_at->copy()->subMinutes(fake()->numberBetween(0, 60))->max($attributes['scheduled_at']);

                    return $attributes;
                })
                ->all(),

            IncidentStatus::Rejete => [fake()->boolean(60)
                ? array_merge($this->attributes($incident, InterventionStatus::Terminee, $start), ['title' => 'Contrôle sur place', 'report' => 'Contrôle sur place : aucune anomalie constatée sur le réseau public, problème lié à l\'installation privée.'])
                : $this->attributes($incident, InterventionStatus::Annulee, $start)],

            default => [],
        };
    }

    /** @return array<string, mixed> */
    protected function attributes(Incident $incident, InterventionStatus $status, Carbon $scheduledAt): array
    {
        $hours = fake()->randomElement([1, 1.5, 2, 3, 4, 6, 8]);

        return [
            'title' => fake()->randomElement(InterventionFactory::TITLES[$incident->type->value]),
            'team' => fake()->randomElement(Intervention::TEAMS),
            'scheduled_at' => $scheduledAt,
            'estimated_hours' => $hours,
            'status' => $status,
            'report' => match ($status) {
                InterventionStatus::Terminee => fake()->randomElement(InterventionFactory::REPORTS),
                InterventionStatus::Annulee => 'Intervention annulée : doublon d\'une intervention déjà programmée.',
                default => null,
            },
            'completed_at' => $status === InterventionStatus::Terminee
                ? $scheduledAt->copy()->addMinutes((int) ($hours * 60))->min(now())
                : null,
        ];
    }

    /**
     * @param  Collection<int, User>  $technicians
     * @param  array<string, mixed>  $attributes
     */
    protected function create(Incident $incident, $technicians, array $attributes): void
    {
        Intervention::factory()->for($incident)->create($attributes + [
            'technician_id' => $technicians->isNotEmpty() && fake()->boolean(85) ? $technicians->random()->id : null,
        ]);
    }
}
