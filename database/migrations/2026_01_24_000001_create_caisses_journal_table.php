<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caisses_journal', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('date');

            $table->bigInteger('fond_ouverture')->default(0);
            $table->bigInteger('total_encaissements')->default(0);
            $table->bigInteger('total_decaissements')->default(0);
            $table->bigInteger('solde_theorique')->default(0);

            $table->bigInteger('solde_reel')->nullable();
            $table->boolean('cloture')->default(false);
            $table->text('observations')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caisses_journal');
    }
};

