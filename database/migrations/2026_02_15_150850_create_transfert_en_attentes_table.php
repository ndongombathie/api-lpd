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
        Schema::create('transfert_en_attentes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('produit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->bigInteger('quantite')->default(0);
            $table->bigInteger('nombre_carton')->default(0);
            $table->bigInteger('seuil')->default(0);
            $table->bigInteger('prix_unite_carton')->default(0);
            $table->bigInteger('prix_vente_detail')->default(0);
            $table->bigInteger('prix_vente_gros')->default(0);
            $table->bigInteger('prix_seuil_detail')->nullable()->default(0);
            $table->bigInteger('prix_seuil_gros')->nullable()->default(0);
            $table->enum('status', ['en_attente', 'valide'])->default('en_attente');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfert_en_attentes');
    }
};
