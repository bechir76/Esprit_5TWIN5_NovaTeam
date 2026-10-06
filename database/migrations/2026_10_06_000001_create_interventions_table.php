<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('interventions', function (Blueprint $table) {
            $table->id();
            // Cascade : les interventions d'un incident supprimé sont supprimées avec lui
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('team', 100);
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('scheduled_at')->index();
            $table->decimal('estimated_hours', 4, 1);
            $table->string('status')->default('planifiee')->index();
            $table->text('report')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interventions');
    }
};
