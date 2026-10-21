<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plan d'inspections (prochaine inspection, alerte de retard envoyée une fois)
     * et score de risque prédictif (calculé par le code, texte rédigé par l'IA ou par des règles).
     */
    public function up(): void
    {
        Schema::table('infrastructures', function (Blueprint $table) {
            $table->date('next_inspection_at')->nullable()->index()->after('last_inspection_at');
            $table->timestamp('inspection_overdue_notified_at')->nullable()->after('next_inspection_at');
            $table->unsignedTinyInteger('risk_score')->nullable()->index()->after('is_public');
            $table->string('risk_level', 10)->nullable()->after('risk_score');
            $table->json('risk_factors')->nullable()->after('risk_level');
            $table->text('risk_summary')->nullable()->after('risk_factors');
            $table->json('risk_recommendations')->nullable()->after('risk_summary');
            $table->timestamp('risk_computed_at')->nullable()->after('risk_recommendations');
            $table->string('risk_source', 10)->nullable()->after('risk_computed_at');
        });
    }

    public function down(): void
    {
        Schema::table('infrastructures', function (Blueprint $table) {
            $table->dropIndex(['next_inspection_at']);
            $table->dropIndex(['risk_score']);
            $table->dropColumn([
                'next_inspection_at', 'inspection_overdue_notified_at', 'risk_score', 'risk_level', 'risk_factors',
                'risk_summary', 'risk_recommendations', 'risk_computed_at', 'risk_source',
            ]);
        });
    }
};
