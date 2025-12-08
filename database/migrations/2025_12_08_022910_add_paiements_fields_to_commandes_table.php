<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaiementsFieldsToCommandesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->unsignedBigInteger('montant_paye')
                  ->default(0)
                  ->after('total');

            $table->unsignedBigInteger('reste_a_payer')
                  ->nullable()
                  ->after('montant_paye');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->dropColumn(['montant_paye', 'reste_a_payer']);
        });
    }
}
