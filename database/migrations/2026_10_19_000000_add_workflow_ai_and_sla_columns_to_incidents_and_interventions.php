<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Module incidents : colonnes ajoutées uniquement (aucune colonne existante n'est renommée ni supprimée,
     * le module projets lit infrastructure_id, severity, status, title, user_id et created_at).
     */
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            // Historique des statuts (qui, quand, ancien / nouveau statut, commentaire), écrit par IncidentObserver
            $table->json('status_history')->nullable()->after('resolved_at');
            // Doublons : signalement rattaché à un incident principal, qui compte ses signalements
            $table->foreignId('duplicate_of_id')->nullable()->after('status_history')->constrained('incidents')->nullOnDelete();
            $table->unsignedInteger('reports_count')->default(1)->after('duplicate_of_id');
            // Délai d'intervention dépassé (commande incidents:check-sla, une seule fois par incident)
            $table->dateTime('sla_breached_at')->nullable()->index()->after('reports_count');
            // Analyse IA (valeurs proposées à côté des valeurs choisies, pour mesurer l'accord IA / humain)
            $table->string('ai_type')->nullable()->after('sla_breached_at');
            $table->string('ai_severity')->nullable()->after('ai_type');
            $table->unsignedTinyInteger('ai_urgency_score')->nullable()->index()->after('ai_severity');
            $table->text('ai_urgency_reason')->nullable()->after('ai_urgency_score');
            $table->text('ai_summary')->nullable()->after('ai_urgency_reason');
            $table->text('ai_photo_analysis')->nullable()->after('ai_summary');
            $table->dateTime('ai_analyzed_at')->nullable()->after('ai_photo_analysis');
            // Incidents liés (commande incidents:cluster) et diagnostic proposé pour le groupe
            $table->string('cluster_key', 20)->nullable()->index()->after('ai_analyzed_at');
            $table->text('cluster_diagnosis')->nullable()->after('cluster_key');
        });

        Schema::table('interventions', function (Blueprint $table) {
            $table->dateTime('started_at')->nullable()->after('report');
        });
    }

    public function down(): void
    {
        Schema::table('interventions', function (Blueprint $table) {
            $table->dropColumn('started_at');
        });

        Schema::table('incidents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('duplicate_of_id');
            $table->dropIndex(['sla_breached_at']);
            $table->dropIndex(['ai_urgency_score']);
            $table->dropIndex(['cluster_key']);
            $table->dropColumn([
                'status_history', 'reports_count', 'sla_breached_at', 'ai_type', 'ai_severity', 'ai_urgency_score',
                'ai_urgency_reason', 'ai_summary', 'ai_photo_analysis', 'ai_analyzed_at', 'cluster_key', 'cluster_diagnosis',
            ]);
        });
    }
};
