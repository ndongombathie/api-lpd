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
        Schema::table('transfert_en_attentes', function (Blueprint $table) {
            $table->bigInteger('quantite_initial')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transfert_en_attentes', function (Blueprint $table) {
            $table->dropColumn('quantite_initial');
        });
    }
};
