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
        Schema::table('stock_boutiques', function (Blueprint $table) {
            $table->foreignUuid('transfert_en_attente_id')->nullable()->constrained('transfert_en_attentes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_boutiques', function (Blueprint $table) {
            $table->dropForeign(['transfert_en_attente_id']);
            $table->dropColumn('transfert_en_attente_id');
        });
    }
};
