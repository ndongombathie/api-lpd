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
        Schema::create('enregistrer_versements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('caissier_id')->nullable()->foreignId('users')->cascadeOnDelete();
            $table->bigInteger('montant');
            $table->text('observation')->nullable();
            $table->date('date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enregistrer_versements');
    }
};
