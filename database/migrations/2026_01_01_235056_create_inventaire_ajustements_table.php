<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventaire_ajustements', function (Blueprint $table) {
            $table->id();

            // 🗓️ Date du comptage
            $table->date('date_comptage');

            // 🔖 Infos produit
            $table->string('produit');
            $table->string('categorie')->nullable();
            $table->string('fournisseur')->nullable();

            // 📦 Stocks
            $table->unsignedInteger('stock_theorique');
            $table->unsignedInteger('stock_reel');

            // 🔀 Ecart & valeur en FCFA (peut être négatif)
            $table->integer('ecart');              // stock_reel - stock_theorique
            $table->bigInteger('valeur_ecart');    // en XOF

            // 📝 Motif / remarque
            $table->string('motif')->nullable();

            // 👤 Qui a fait l’ajustement (uuid)
            $table->uuid('user_id')->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventaire_ajustements');
    }
};
