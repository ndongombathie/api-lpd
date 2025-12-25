<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decaissements', function (Blueprint $table) {
            // Si tu n'as PAS de foreign keys, pas besoin de dropForeign

            // ⚠️ nécessite doctrine/dbal pour change()
            $table->string('demandeur_id', 36)->nullable()->change();
            $table->string('traite_par_id', 36)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('decaissements', function (Blueprint $table) {
            $table->unsignedBigInteger('demandeur_id')->nullable()->change();
            $table->unsignedBigInteger('traite_par_id')->nullable()->change();
        });
    }
};
