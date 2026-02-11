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
        Schema::create('mouvement_stocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('source')->nullable();
            $table->string('destination')->nullable();
            $table->uuid('produit_id');
            $table->integer('quantite');
            $table->enum('type', ['entree', 'sortie']);
            $table->timestamp('date')->useCurrent();
            $table->timestamps();

            $table->foreign('produit_id')->references('id')->on('produits')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mouvement_stocks');
    }
};
