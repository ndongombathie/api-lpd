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
        Schema::table('caisses_journal', function (Blueprint $table) {
            $table->uuid('caissier_id')->nullable();
            $table->foreign('caissier_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('caisses_journal', function (Blueprint $table) {
            // Supprimer la colonne caissier_id
            $table->dropForeign(['caissier_id']);
            $table->dropColumn('caissier_id');
        });
    }
};
