<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decaissements', function (Blueprint $table) {
            if (Schema::hasColumn('decaissements', 'motif_refus')) {
                $table->dropColumn('motif_refus');
            }
        });
    }

    public function down(): void
    {
        Schema::table('decaissements', function (Blueprint $table) {
            if (!Schema::hasColumn('decaissements', 'motif_refus')) {
                $table->text('motif_refus')->nullable();
            }
        });
    }
};
