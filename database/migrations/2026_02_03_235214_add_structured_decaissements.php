<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 🔄 Ajout des champs modernes
        Schema::table('decaissements', function (Blueprint $table) {
            $table->string('motif_global')->nullable()->after('motif');
            $table->string('methode_prevue')->nullable()->after('methode_paiement');
            $table->date('date_prevue')->nullable()->after('date');
            $table->bigInteger('montant_total')->default(0)->after('montant');
        });
    }

    public function down(): void
    {
        Schema::table('decaissements', function (Blueprint $table) {
            $table->dropColumn(['motif_global','methode_prevue','date_prevue','montant_total']);
        });
    }
};
