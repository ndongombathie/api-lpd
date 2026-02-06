<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decaissements', function (Blueprint $table) {

            if (!Schema::hasColumn('decaissements', 'motif_global')) {
                $table->string('motif_global')->nullable()->after('motif');
            }

            if (!Schema::hasColumn('decaissements', 'methode_prevue')) {
                $table->string('methode_prevue')->nullable()->after('methode_paiement');
            }

            if (!Schema::hasColumn('decaissements', 'date_prevue')) {
                $table->date('date_prevue')->nullable()->after('date');
            }

            if (!Schema::hasColumn('decaissements', 'montant_total')) {
                $table->bigInteger('montant_total')->nullable()->after('montant');
            }
        });
    }

    public function down(): void
    {
        Schema::table('decaissements', function (Blueprint $table) {

            if (Schema::hasColumn('decaissements', 'motif_global')) {
                $table->dropColumn('motif_global');
            }

            if (Schema::hasColumn('decaissements', 'methode_prevue')) {
                $table->dropColumn('methode_prevue');
            }

            if (Schema::hasColumn('decaissements', 'date_prevue')) {
                $table->dropColumn('date_prevue');
            }

            if (Schema::hasColumn('decaissements', 'montant_total')) {
                $table->dropColumn('montant_total');
            }
        });
    }
};
