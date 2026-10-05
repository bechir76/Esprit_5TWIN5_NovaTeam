<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_analyses', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 14)->unique();
            // Cascade : si l'infrastructure est supprimée, toutes ses analyses le sont aussi
            $table->foreignId('infrastructure_id')->constrained()->cascadeOnDelete();
            $table->dateTime('sampled_at');
            $table->string('sampling_point', 150);
            $table->string('laboratory', 150)->nullable();
            $table->foreignId('sampled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('en_attente')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_analyses');
    }
};
