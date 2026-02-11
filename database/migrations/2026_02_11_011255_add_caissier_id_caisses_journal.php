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
            // Ajouter la colonne caissier_id
            $table->uuid('caissier_id')->nullable();
            $table->foreignUuid('caissier_id')->nullable()->constrained('caissiers')->onDelete('set null');
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
