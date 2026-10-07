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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('slug')->unique();
            $table->string('category')->index();
            $table->text('description');
            $table->decimal('target_budget', 12, 2);
            $table->date('start_date');
            $table->date('end_date')->index();
            $table->string('status')->default('brouillon')->index();
            $table->string('image_path')->nullable();
            $table->boolean('is_public')->default(false);
            // Le projet est conservé si l'ouvrage est supprimé
            $table->foreignId('infrastructure_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
