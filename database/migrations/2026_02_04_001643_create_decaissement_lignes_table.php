<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('decaissement_lignes', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('decaissement_id')
                ->constrained('decaissements')
                ->cascadeOnDelete();

            $table->string('libelle');
            $table->bigInteger('montant');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decaissement_lignes');
    }
};
