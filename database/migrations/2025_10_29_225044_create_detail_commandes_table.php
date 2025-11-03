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
        Schema::create('detail_commandes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('commande_id');
            $table->uuid('produit_id');
            $table->integer('quantite');
            $table->integer('prix_unitaire');
            $table->timestamps();

            $table->foreign('commande_id')->references('id')->on('commandes')->cascadeOnDelete();
            $table->foreign('produit_id')->references('id')->on('produits')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_commandes');
    }
};
