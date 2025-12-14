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
        Schema::create('produits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom');
            $table->string('code')->unique();
            $table->string('categorie')->default('autre');
            $table->integer('prix_vente_detail')->default(0);
            $table->integer('prix_vente_gros')->default(0);
            $table->integer('prix_achat')->nullable()->default(0);
            $table->integer('prix_seuil_detail')->nullable()->default(0);
            $table->integer('prix_seuil_gros')->nullable()->default(0);
            $table->bigInteger('quantite')->default(0);
            $table->bigInteger('stock_global')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produits');
    }
};
