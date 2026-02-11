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
        Schema::create('paiements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('caissier_id');
            $table->foreign('caissier_id')->references('id')->on('users')->cascadeOnDelete();
            $table->uuid('commande_id');
            $table->integer('montant');
            $table->string('type_paiement'); // cash, mobile, carte, virement
            $table->timestamp('date')->useCurrent();
            $table->integer('reste_du')->default(0);
            $table->timestamps();
            $table->foreign('commande_id')->references('id')->on('commandes')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
