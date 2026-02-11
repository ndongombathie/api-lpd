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
        Schema::create('entree_sortie_boutiques', function (Blueprint $table) {
             $table->uuid('id')->primary();
            $table->foreignUuid('produit_id')->constrained('produits');
            $table->integer('quantite_avant');
            $table->integer('quantite_apres');
            $table->integer('nombre_fois');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entree_sortie_boutiques');
    }
};
