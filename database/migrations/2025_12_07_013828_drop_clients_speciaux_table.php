<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('clients_speciaux');
    }

    public function down(): void
    {
        // (facultatif) recréer la table si tu veux, mais tu peux laisser vide
        // ou remettre l'ancien schéma si nécessaire.
    }
};
