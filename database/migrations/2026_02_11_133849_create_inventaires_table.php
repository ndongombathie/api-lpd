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
        Schema::create('inventaires', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('type',['Boutique','Depot']);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->date('date');
            $table->bigInteger('prix_achat_total')->default(0);
            $table->bigInteger('prix_valeur_sortie_total')->default(0);
            $table->bigInteger('valeur_estimee_total')->default(0);
            $table->bigInteger('benefice_total')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventaires');
    }
};
