<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients_speciaux', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('boutique_id');
            $table->string('nom');
            $table->string('contact', 20);
            $table->string('entreprise');
            $table->string('adresse');
            $table->timestamps();

            $table->foreign('boutique_id')
                ->references('id')
                ->on('boutiques')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients_speciaux');
    }
};
