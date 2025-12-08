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
        Schema::create('produits', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Identité produit
            $table->string('nom');                 // ex: Topics
            $table->string('code')->unique();      // code-barres scannable
            $table->string('categorie')->nullable(); // ex: Cahier, Stylo, etc.

            // Stock physique (lot actuel)
            $table->unsignedInteger('nombre_cartons')->default(0);      // nb cartons / boîtes
            $table->unsignedInteger('unites_par_carton')->default(0);   // nb unités par carton
            $table->unsignedInteger('stock_global')->default(0);        // calculé = cartons * unités

            // Seuil d’alerte
            $table->unsignedInteger('quantite_seuil')->default(0);      // seuil d’alerte global

            // Prix de référence (détail)
            $table->unsignedBigInteger('prix_basique_detail')->nullable(); // prix marché détail
            $table->unsignedBigInteger('prix_seuil_detail')->nullable();   // prix mini à ne pas descendre

            // Prix de référence (gros)
            $table->unsignedBigInteger('prix_basique_gros')->nullable();   // prix marché carton/boîte
            $table->unsignedBigInteger('prix_seuil_gros')->nullable();     // prix mini à ne pas descendre en gros

            // Coût d’acquisition total du lot (pour inventaire / marge)
            $table->unsignedBigInteger('cout_acquisition_total')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produits');
    }
};
