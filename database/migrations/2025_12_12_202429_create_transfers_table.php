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
        Schema::create('transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('boutique_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('produit_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('quantite')->default(0);
            $table->enum('status', ['en_attente', 'valide'])->default('en_attente');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfers');
        Schema::table('stock_boutiques', function (Blueprint $table) {
            $table->dropForeign(['produit_id']);
            $table->dropColumn('produit_id');
        });
    }
};
