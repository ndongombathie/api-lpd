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
        Schema::table('caisses_journal', function (Blueprint $table) {
            // Ajouter la colonne nombre_paiements
            $table->bigInteger('nombre_paiements')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('caisses_journal', function (Blueprint $table) {
            // Supprimer la colonne nombre_paiements
            $table->dropColumn('nombre_paiements');
        });
    }
};
