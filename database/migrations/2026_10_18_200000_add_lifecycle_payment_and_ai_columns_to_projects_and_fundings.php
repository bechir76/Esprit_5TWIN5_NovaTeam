<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module projets : cycle de vie en anglais (draft → open → funded / failed → in_progress → completed),
 * paiement Stripe et remboursement des dons, cofinancement, anonymat et priorisation IA.
 * Aucune nouvelle table métier : seulement des colonnes sur projects et fundings.
 */
return new class extends Migration
{
    /** Anciens statuts français => nouveaux statuts. */
    private const STATUSES = [
        'brouillon' => 'draft',
        'en_collecte' => 'open',
        'finance' => 'funded',
        'en_travaux' => 'in_progress',
        'termine' => 'completed',
    ];

    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('status')->default('draft')->change();
            $table->unsignedTinyInteger('ai_priority_score')->nullable()->index();
            $table->text('ai_priority_reason')->nullable();
            $table->timestamp('ai_scored_at')->nullable();
            // Notification « projet à 50 % » envoyée une seule fois
            $table->timestamp('milestone_notified_at')->nullable();
        });

        foreach (self::STATUSES as $old => $new) {
            DB::table('projects')->where('status', $old)->update(['status' => $new]);
        }

        Schema::table('fundings', function (Blueprint $table) {
            $table->string('status')->default('pending')->index();
            $table->string('stripe_session_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_refund_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('receipt_number', 16)->nullable()->unique();
            $table->boolean('is_matching')->default(false);
            $table->boolean('is_anonymous')->default(false);
            // Don citoyen à l'origine d'un cofinancement (un seul cofinancement par don)
            $table->foreignId('matched_funding_id')->nullable()->unique()->constrained('fundings')->nullOnDelete();
        });

        // Les financements existants (saisis par le personnel ou simulés) sont considérés comme encaissés
        DB::table('fundings')->update(['status' => 'confirmed', 'paid_at' => DB::raw('funded_at')]);
    }

    public function down(): void
    {
        Schema::table('fundings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('matched_funding_id');
            $table->dropUnique(['stripe_session_id']);
            $table->dropUnique(['receipt_number']);
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'stripe_session_id', 'stripe_payment_intent_id', 'stripe_refund_id', 'paid_at', 'receipt_number', 'is_matching', 'is_anonymous']);
        });

        foreach (self::STATUSES as $old => $new) {
            DB::table('projects')->where('status', $new)->update(['status' => $old]);
        }
        DB::table('projects')->where('status', 'failed')->update(['status' => 'termine']);

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['ai_priority_score']);
            $table->dropColumn(['ai_priority_score', 'ai_priority_reason', 'ai_scored_at', 'milestone_notified_at']);
            $table->string('status')->default('brouillon')->change();
        });
    }
};
