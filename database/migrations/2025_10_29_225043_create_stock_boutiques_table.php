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
        Schema::create('stock_boutiques', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('boutique_id');
            $table->uuid('produit_id');
            $table->integer('quantite')->default(0);
            $table->timestamps();

            $table->foreign('boutique_id')->references('id')->on('boutiques')->cascadeOnDelete();
            $table->foreign('produit_id')->references('id')->on('produits')->cascadeOnDelete();
            $table->unique(['boutique_id', 'produit_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_boutiques');
    }
};
