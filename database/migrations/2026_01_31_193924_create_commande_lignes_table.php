<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commande_lignes', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('commande_id');
            $table->uuid('produit_id')->nullable();

            $table->string('libelle');
            $table->string('ref')->nullable();

            $table->integer('quantite');
            $table->integer('quantite_unites')->nullable();

            $table->enum('mode_vente', ['detail', 'gros'])->default('detail');

            $table->decimal('prix_unitaire', 12, 2);
            $table->decimal('total_ht', 14, 2);
            $table->decimal('total_ttc', 14, 2);

            $table->timestamps();

            $table->foreign('commande_id')->references('id')->on('commandes')->cascadeOnDelete();
            $table->foreign('produit_id')->references('id')->on('produits')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commande_lignes');
    }
};

