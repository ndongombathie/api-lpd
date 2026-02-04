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
            $table->foreignUuid('categorie_id')->constrained('categories')->onDelete('cascade');
            $table->foreignUuid('fournisseur_id')->constrained('fournisseurs')->onDelete('cascade');
            $table->bigInteger('unite_carton')->default(0);
            $table->bigInteger('prix_unite_carton')->default(0);
            $table->bigInteger('prix_vente_detail')->default(0);
            $table->bigInteger('prix_vente_gros')->default(0);
            $table->bigInteger('prix_total')->default(0);
            $table->bigInteger('prix_achat')->nullable()->default(0);
            $table->bigInteger('prix_seuil_detail')->nullable()->default(0);
            $table->bigInteger('prix_seuil_gros')->nullable()->default(0);
            $table->bigInteger('nombre_carton')->default(0);
            $table->bigInteger('stock_global')->default(0);
            $table->bigInteger('stock_seuil')->default(0);
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
