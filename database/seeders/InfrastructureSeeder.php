<?php

namespace Database\Seeders;

use App\Enums\InfrastructureStatus as Status;
use App\Enums\InfrastructureType as Type;
use App\Models\Infrastructure;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class InfrastructureSeeder extends Seeder
{
    /** Centre approximatif de chaque zone : les ouvrages générés sont placés autour. */
    protected const CENTERS = [
        'Tunis Centre' => [36.800, 10.170],
        'Ariana Nord' => [36.880, 10.190],
        'La Soukra' => [36.870, 10.240],
        'Ben Arous' => [36.745, 10.235],
        'Mégrine' => [36.770, 10.230],
        'La Manouba' => [36.810, 10.095],
    ];

    /** Ouvrages du template (tableaux, carte, projets, incidents) : zone => [code, nom, type, capacité, unité, état, inspection, année, matériau, lat, lng]. */
    protected const TEMPLATE = [
        'Tunis Centre' => [
            ['RES-TUN-001', 'Réservoir Belvédère', Type::Reservoir, 12000, 'm3', Status::Bon, '2026-09-14', 1978, 'Béton armé', 36.8250, 10.1760],
            ['CND-TUN-021', 'Conduite fonte Bab Saadoun', Type::Conduite, 2.4, 'km', Status::ASurveiller, '2026-06-05', 1962, 'Fonte grise', 36.8070, 10.1650],
        ],
        'Ariana Nord' => [
            ['PMP-ARI-003', 'Station de pompage Ariana Nord', Type::Pompage, 150, 'l_s', Status::Critique, '2026-08-02', 1994, 'Acier', 36.8800, 10.1900],
            ['PMP-ARI-007', 'Station de pompage de Raoued', Type::Pompage, 90, 'l_s', Status::ASurveiller, '2026-05-18', 2004, 'Acier', 36.9300, 10.1850],
            ['CND-ARI-012', 'Conduite DN 300 Ariana Nord', Type::Conduite, 3.2, 'km', Status::ASurveiller, '2026-07-30', 1989, 'Fonte ductile', 36.8650, 10.1950],
        ],
        'La Soukra' => [
            ['RES-ARI-004', 'Réservoir de La Soukra', Type::Reservoir, 5000, 'm3', Status::Critique, '2026-03-12', 1987, 'Béton armé', 36.8750, 10.2450],
        ],
        'Ben Arous' => [
            ['CND-BAR-017', 'Conduite DN 600 Ben Arous', Type::Conduite, 4.1, 'km', Status::ASurveiller, '2026-07-19', 1981, 'Fonte ductile', 36.7530, 10.2300],
            ['FOR-BAR-003', 'Forage de Mornag F3', Type::Forage, 35, 'l_s', Status::Critique, '2026-09-21', 1999, 'Acier', 36.7080, 10.2900],
        ],
        'Mégrine' => [
            ['RES-BAR-002', 'Réservoir Mégrine', Type::Reservoir, 8000, 'm3', Status::Bon, '2026-09-01', 1983, 'Béton armé', 36.7700, 10.2330],
        ],
        'La Manouba' => [
            ['TRT-MAN-001', 'Station de traitement Ghdir El Golla', Type::Traitement, 4000, 'l_s', Status::Bon, '2026-09-28', 1985, 'Béton armé', 36.8050, 10.0900],
        ],
    ];

    /** 5 à 8 ouvrages par zone : ceux du template, complétés par la factory. */
    public function run(): void
    {
        foreach (Zone::all() as $zone) {
            $fixed = self::TEMPLATE[$zone->name] ?? [];

            foreach ($fixed as [$code, $name, $type, $capacity, $unit, $status, $inspection, $year, $material, $lat, $lng]) {
                Infrastructure::factory()->for($zone)->create([
                    'code' => $code,
                    'name' => $name,
                    'type' => $type,
                    'capacity' => $capacity,
                    'capacity_unit' => $unit,
                    'status' => $status,
                    'last_inspection_at' => $inspection,
                    'commissioned_year' => $year,
                    'material' => $material,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'is_public' => true,
                ]);
            }

            [$lat, $lng] = self::CENTERS[$zone->name] ?? [36.80, 10.18];
            $missing = max(0, fake()->numberBetween(5, 8) - count($fixed));

            Infrastructure::factory($missing)->for($zone)->create(fn () => [
                'latitude' => round($lat + fake()->randomFloat(4, -0.025, 0.025), 6),
                'longitude' => round($lng + fake()->randomFloat(4, -0.03, 0.03), 6),
            ]);
        }
    }
}
