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
        Schema::create('audit_logs', function (Blueprint $table) {

            // ID UUID (cohérent avec ton projet actuel)
            $table->uuid('id')->primary();

            /*
            |--------------------------------------------------------------------------
            | Identification du module
            |--------------------------------------------------------------------------
            | fournisseurs | clients
            */
            $table->string('module')->index();

            /*
            |--------------------------------------------------------------------------
            | Type d'action
            |--------------------------------------------------------------------------
            | creation | modification | suppression
            */
            $table->string('action')->index();

            /*
            |--------------------------------------------------------------------------
            | Cible de l'action
            |--------------------------------------------------------------------------
            */
            $table->string('cible_id')->nullable();
            $table->string('cible_nom');

            /*
            |--------------------------------------------------------------------------
            | Utilisateur ayant effectué l'action
            |--------------------------------------------------------------------------
            */
            $table->uuid('user_id')->nullable()->index();

            /*
            |--------------------------------------------------------------------------
            | Description courte affichée dans le tableau
            |--------------------------------------------------------------------------
            */
            $table->text('details')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Données avant / après modification
            |--------------------------------------------------------------------------
            | Utilisé dans le modal "Voir détails"
            */
            $table->json('avant')->nullable();
            $table->json('apres')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Dates
            |--------------------------------------------------------------------------
            */
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
