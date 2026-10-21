<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Dernier plan de maintenance généré pour la zone (ouvrages choisis par le code, texte de l'IA ou des règles). */
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->json('maintenance_plan')->nullable()->after('description');
            $table->timestamp('maintenance_plan_at')->nullable()->after('maintenance_plan');
            $table->string('maintenance_plan_source', 10)->nullable()->after('maintenance_plan_at');
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn(['maintenance_plan', 'maintenance_plan_at', 'maintenance_plan_source']);
        });
    }
};
