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
        // Sécurité : si la table existe déjà, on ne la recrée pas
        if (Schema::hasTable('decaissement_lignes')) {
            return;
        }

        Schema::create('decaissement_lignes', function (Blueprint $table) {
            $table->id();

            // Lien vers la demande de décaissement
            $table->foreignId('decaissement_id')
                ->constrained('decaissements')   // FK vers la table decaissements
                ->onDelete('cascade');           // si un décaissement est supprimé, on supprime ses lignes

            // Libellé de la ligne (facture, frais, etc.)
            $table->string('libelle');

            // Montant de la ligne (en FCFA)
            $table->unsignedBigInteger('montant')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('decaissement_lignes');
    }
};
