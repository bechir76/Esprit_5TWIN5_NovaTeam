<?php

namespace App\Models;

use App\Enums\WaterParameter;
use Database\Factories\MeasurementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mesure d'un paramètre physico-chimique ou bactériologique
 * rattachée à une analyse d'eau (WaterAnalysis).
 *
 * Lors de l'enregistrement, `unit` et `is_compliant` sont remplis
 * automatiquement depuis l'enum WaterParameter, et le statut de
 * l'analyse parente est recalculé.
 */
class Measurement extends Model
{
    /** @use HasFactory<MeasurementFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'water_analysis_id',
        'parameter',
        'value',
        'unit',
        'is_compliant',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'parameter'   => WaterParameter::class,
        'value'       => 'decimal:4',
        'is_compliant' => 'boolean',
    ];

    // ---------------------------------------------------------------- Hooks

    protected static function booted(): void
    {
        // Avant la création ou la mise à jour : déduire l'unité et la conformité depuis l'enum
        static::saving(function (self $measurement): void {
            $param = $measurement->parameter instanceof WaterParameter
                ? $measurement->parameter
                : WaterParameter::from($measurement->parameter);

            $measurement->unit         = $param->unit();
            $measurement->is_compliant = $param->isCompliant((float) $measurement->value);
        });

        // Après toute modification : recalculer le statut de l'analyse parente,
        // détecter les anomalies statistiques (B1) et vérifier les alertes de seuil (A2)
        static::saved(function (self $measurement): void {
            $measurement->waterAnalysis->recalculateStatus();

            // Détection d'anomalie et alertes en différé pour ne pas bloquer l'enregistrement
            dispatch(new \App\Jobs\AnalyzeWaterMeasurement($measurement))->afterCommit();
        });

        static::deleted(function (self $measurement): void {
            $measurement->waterAnalysis->recalculateStatus();
        });
    }

    // ---------------------------------------------------------------- Relations

    /** @return BelongsTo<WaterAnalysis, $this> */
    public function waterAnalysis(): BelongsTo
    {
        return $this->belongsTo(WaterAnalysis::class);
    }
}
