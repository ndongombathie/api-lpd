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
        Schema::table('paiements', function (Blueprint $table) {
            // 💳 Mode de paiement (Wave, OM, espèces, etc.)
            if (!Schema::hasColumn('paiements', 'mode_paiement')) {
                $table->string('mode_paiement')->nullable()->after('type_paiement');
            }

            // 🚦 Statut du paiement côté caisse / responsable
            // ex : en_attente_caisse | valide | annule
            if (!Schema::hasColumn('paiements', 'statut_paiement')) {
                $table->string('statut_paiement')
                      ->default('en_attente_caisse')
                      ->after('mode_paiement');
            }

            // 📝 Commentaire éventuel
            if (!Schema::hasColumn('paiements', 'commentaire')) {
                $table->text('commentaire')->nullable()->after('statut_paiement');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            if (Schema::hasColumn('paiements', 'commentaire')) {
                $table->dropColumn('commentaire');
            }
            if (Schema::hasColumn('paiements', 'statut_paiement')) {
                $table->dropColumn('statut_paiement');
            }
            if (Schema::hasColumn('paiements', 'mode_paiement')) {
                $table->dropColumn('mode_paiement');
            }
        });
    }
};
