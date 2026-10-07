<?php

namespace Database\Seeders;

use App\Enums\FundingSource as Source;
use App\Enums\ProjectStatus as Status;
use App\Enums\Role;
use App\Models\Funding;
use App\Models\Project;
use App\Models\User;
use Database\Factories\FundingFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * 5 à 25 financements par projet, dont le total respecte le statut du projet :
 *  - brouillon             : quelques engagements, très loin de l'objectif ;
 *  - en collecte           : entre 25 et 90 % de l'objectif ;
 *  - financé / en travaux / terminé : objectif atteint (jusqu'à 6 % de dépassement).
 * Répartition : municipalité (le plus gros montant), associations, entreprises et citoyens existants.
 * Les financements sont créés dans l'ordre chronologique (références FIN-2026-0001…).
 */
class FundingSeeder extends Seeder
{
    public function run(): void
    {
        $citizens = User::where('role', Role::Citoyen->value)->where('is_active', true)->get();
        $testCitizen = User::where('email', 'citoyen@aquasecure.tn')->first();
        $citizens = $citizens->reject(fn (User $user) => $user->is($testCitizen))->values();
        $rows = collect();
        $testCitizenProjects = 0;

        foreach (Project::with('infrastructure.zone')->orderBy('id')->get() as $project) {
            [$count, $ratio] = match ($project->status) {
                Status::Brouillon => [5, fake()->randomFloat(2, 0.03, 0.15)],
                Status::EnCollecte => [fake()->numberBetween(8, 25), fake()->randomFloat(2, 0.25, 0.90)],
                default => [fake()->numberBetween(10, 25), fake()->randomFloat(2, 1.00, 1.06)],
            };
            $total = (int) ceil((float) $project->target_budget * $ratio);

            // Nombre de financements par source : au moins une municipalité et deux citoyens
            $groups = [Source::Municipalite->value => $count >= 15 ? 2 : 1, Source::Association->value => $count >= 10 ? 2 : 1];
            $groups[Source::Entreprise->value] = $count >= 8 ? fake()->numberBetween(1, 2) : 0;
            $groups[Source::Citoyen->value] = $count - array_sum($groups);

            // Montants : petits dons citoyens (600 TND en moyenne au plus), entreprises 18 %, associations 12 %, le reste à la municipalité
            $amounts = [
                Source::Citoyen->value => min($total * 0.2, $groups[Source::Citoyen->value] * 600),
                Source::Entreprise->value => $groups[Source::Entreprise->value] ? $total * 0.18 : 0,
                Source::Association->value => $total * 0.12,
            ];
            $amounts[Source::Municipalite->value] = $total - array_sum($amounts);

            $projectRows = collect();
            foreach ($groups as $source => $n) {
                foreach ($this->split($amounts[$source], $n, $source === Source::Citoyen->value) as $amount) {
                    $projectRows->push(['source' => Source::from($source), 'amount' => $amount]);
                }
            }

            // Arrondi : l'écart est reporté sur le premier financement municipal (total exact)
            $first = $projectRows->search(fn ($r) => $r['source'] === Source::Municipalite);
            $projectRows[$first] = ['source' => Source::Municipalite, 'amount' => $projectRows[$first]['amount'] + $total - $projectRows->sum('amount')];

            // Donateurs, citoyens existants (dont la citoyenne de test sur deux projets publics) et dates
            $municipality = 'Municipalité de '.($project->infrastructure?->zone->municipality ?? 'Tunis');
            $donors = $citizens->shuffle()->values();
            $withTestCitizen = $testCitizen && $project->is_public && $project->status !== Status::Brouillon && $testCitizenProjects < 2;
            $testCitizenProjects += (int) $withTestCitizen;
            $c = 0;

            foreach ($projectRows as $row) {
                $user = null;
                $donor = match ($row['source']) {
                    Source::Municipalite => $municipality,
                    Source::Citoyen => ($user = ($withTestCitizen && $c === 0 ? $testCitizen : $donors[$c % max(1, $donors->count())] ?? null))?->name ?? fake('fr_FR')->name(),
                    default => FundingFactory::donorFor($row['source']),
                };
                if ($row['source'] === Source::Citoyen) {
                    $c++;
                }

                $rows->push([
                    'project_id' => $project->id,
                    'source_type' => $row['source'],
                    'donor_name' => $donor,
                    'user_id' => $user?->id,
                    'amount' => $row['amount'],
                    'funded_at' => $this->dateFor($project, $row['source']),
                    'note' => $row['source'] === Source::Municipalite ? 'Engagement voté en conseil municipal.' : null,
                ]);
            }
        }

        foreach ($rows->sortBy(fn (array $r) => $r['funded_at']->timestamp)->values() as $row) {
            Funding::create($row);
        }
    }

    /**
     * Partage un montant en $n parts entières aléatoires (au moins 5 TND chacune ; 10 000 TND au plus pour un citoyen).
     *
     * @return list<int>
     */
    protected function split(float $amount, int $n, bool $citizen): array
    {
        if ($n === 0) {
            return [];
        }

        $weights = array_map(fn () => fake()->numberBetween(1, 10), range(1, $n));
        $sum = array_sum($weights);

        return array_map(function (int $w) use ($amount, $sum, $citizen) {
            $part = max(5, (int) round($amount * $w / $sum));

            return $citizen ? min(10000, $part) : $part;
        }, $weights);
    }

    /** Date entre le début de la collecte et aujourd'hui (ou la fin de la collecte) ; la municipalité s'engage en premier. */
    protected function dateFor(Project $project, Source $source): Carbon
    {
        $start = $project->start_date->copy();
        $end = $project->end_date->copy()->min(today());

        if ($source === Source::Municipalite) {
            $end = $start->copy()->addDays((int) max(0, $start->diffInDays($end) / 4));
        }

        return $start->gte($end) ? $end : Carbon::instance(fake()->dateTimeBetween($start, $end))->startOfDay();
    }
}
