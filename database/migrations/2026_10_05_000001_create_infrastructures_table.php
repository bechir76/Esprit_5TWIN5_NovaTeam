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
        Schema::create('infrastructures', function (Blueprint $table) {
            $table->id();
            // Une zone qui contient encore des ouvrages ne peut pas être supprimée
            $table->foreignId('zone_id')->constrained()->restrictOnDelete();
            $table->string('code', 11)->unique();
            $table->string('name', 120);
            $table->string('type')->index();
            $table->string('material')->nullable();
            $table->decimal('capacity', 12, 2);
            $table->string('capacity_unit', 5);
            $table->unsignedSmallInteger('commissioned_year');
            $table->decimal('nominal_pressure', 5, 2)->nullable();
            $table->string('status')->default('bon')->index();
            $table->date('last_inspection_at')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('infrastructures');
    }
};
