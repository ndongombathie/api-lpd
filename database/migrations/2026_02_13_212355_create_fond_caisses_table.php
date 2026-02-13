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
        Schema::create('fond_caisses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('caissier_id')->nullable()->foreignId('users')->cascadeOnDelete();
            $table->date('date')->default(now()->format('Y-m-d'));
            $table->bigInteger('montant')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fond_caisses');
    }
};
