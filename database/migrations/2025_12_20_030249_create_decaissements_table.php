<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decaissements', function (Blueprint $table) {
            $table->id();

            // Référence lisible
            $table->string('reference')->unique()->nullable(); // ex: DEC-2025-0001

            // Date prévue du décaissement
            $table->date('date_prevue');

            // Motif global de la demande
            $table->string('motif_global');

            // Méthode de décaissement (Espèces, Virement, etc.)
            $table->string('methode_prevue', 100);

            // Statut
            $table->enum('statut', ['en attente', 'validé', 'refusé'])
                  ->default('en attente');

            // Montant total (somme des lignes)
            $table->unsignedBigInteger('montant_total')->default(0);

            // Colonnes simples (pas de FK SQL)
            $table->unsignedBigInteger('demandeur_id')->nullable();
            $table->unsignedBigInteger('traite_par_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decaissements');
    }
};
