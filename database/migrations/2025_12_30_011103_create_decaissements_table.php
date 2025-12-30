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
        Schema::create('decaissements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users');
            $table->foreignUuid('caissier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motif')->nullable();
            $table->string('libelle')->nullable();
            $table->bigInteger('montant');
            $table->string('methode_paiement');
            $table->date('date');
            $table->string('statut')->default('en_attente');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('decaissements');
    }
};
