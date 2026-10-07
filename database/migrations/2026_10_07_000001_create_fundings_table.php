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
        Schema::create('fundings', function (Blueprint $table) {
            $table->id();
            // Cascade : les financements d'un projet supprimé sont supprimés avec lui
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('source_type')->index();
            $table->string('donor_name', 150);
            // Contributeur connecté (citoyen) ; l'historique est conservé si le compte est supprimé
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('funded_at')->index();
            $table->string('reference', 13)->unique();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fundings');
    }
};
