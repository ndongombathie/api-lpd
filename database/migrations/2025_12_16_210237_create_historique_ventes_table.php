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
        Schema::create('historique_ventes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendeur_id')->references('id')->on('users');
            $table->foreignUuid('produit_id')->references('id')->on('produits');
            $table->unsignedInteger('quantite');
            $table->integer('prix_unitaire');
            $table->integer('montant');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historique_ventes');
    }
};
