<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_analysis_id')->constrained()->cascadeOnDelete();
            $table->string('parameter', 30);
            $table->decimal('value', 10, 4);
            $table->string('unit', 20);
            $table->boolean('is_compliant')->default(true);
            $table->timestamps();

            // Un paramètre ne peut apparaître qu'une fois par analyse
            $table->unique(['water_analysis_id', 'parameter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurements');
    }
};
