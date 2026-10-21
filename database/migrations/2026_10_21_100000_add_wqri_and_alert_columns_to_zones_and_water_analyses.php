<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A1 : indice de risque sanitaire (WQRI 0-100) calculé par zone.
     * A2 : alertes de dépassement de seuil sur water_analyses.
     * B1 : colonnes IA d'anomalie sur measurements.
     * B2 : prédiction de dégradation sur water_analyses.
     * B5 : fréquence d'échantillonnage recommandée sur infrastructures.
     */
    public function up(): void
    {
        // WQRI par zone (A1)
        Schema::table('zones', function (Blueprint $table) {
            $table->unsignedTinyInteger('wqri_score')->nullable()->index()->after('description');
            $table->string('wqri_level', 10)->nullable()->after('wqri_score');
            $table->json('wqri_factors')->nullable()->after('wqri_level');
            $table->text('wqri_summary')->nullable()->after('wqri_factors');
            $table->timestamp('wqri_computed_at')->nullable()->after('wqri_summary');
        });

        // Alertes de seuil et prédiction de dégradation sur les analyses (A2, B2)
        Schema::table('water_analyses', function (Blueprint $table) {
            $table->boolean('has_alert')->default(false)->index()->after('notes');
            $table->json('alert_parameters')->nullable()->after('has_alert');
            $table->timestamp('alert_dispatched_at')->nullable()->after('alert_parameters');
            $table->text('ai_degradation_prediction')->nullable()->after('alert_dispatched_at');
            $table->timestamp('ai_degradation_at')->nullable()->after('ai_degradation_prediction');
            $table->unsignedSmallInteger('ai_sampling_frequency_days')->nullable()->after('ai_degradation_at');
        });

        // Détection d'anomalie IA sur les mesures (B1)
        Schema::table('measurements', function (Blueprint $table) {
            $table->boolean('is_anomaly')->default(false)->index()->after('is_compliant');
            $table->text('anomaly_explanation')->nullable()->after('is_anomaly');
            $table->decimal('historical_avg', 10, 4)->nullable()->after('anomaly_explanation');
            $table->decimal('historical_stddev', 10, 4)->nullable()->after('historical_avg');
            $table->decimal('z_score', 6, 2)->nullable()->after('historical_stddev');
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->dropIndex(['wqri_score']);
            $table->dropColumn(['wqri_score', 'wqri_level', 'wqri_factors', 'wqri_summary', 'wqri_computed_at']);
        });

        Schema::table('water_analyses', function (Blueprint $table) {
            $table->dropIndex(['has_alert']);
            $table->dropColumn(['has_alert', 'alert_parameters', 'alert_dispatched_at', 'ai_degradation_prediction', 'ai_degradation_at', 'ai_sampling_frequency_days']);
        });

        Schema::table('measurements', function (Blueprint $table) {
            $table->dropIndex(['is_anomaly']);
            $table->dropColumn(['is_anomaly', 'anomaly_explanation', 'historical_avg', 'historical_stddev', 'z_score']);
        });
    }
};
