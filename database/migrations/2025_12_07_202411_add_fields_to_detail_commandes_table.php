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
        Schema::table('detail_commandes', function (Blueprint $table) {
            // Libellé du produit au moment de la vente (sécurise l'historique même si le produit change)
            $table->string('libelle')->nullable()->after('produit_id');

            // "detail" = unités / "gros" = cartons/boites/paquets
            $table->enum('mode_vente', ['detail', 'gros'])
                  ->default('detail')
                  ->after('libelle');

            // Quantité réelle en UNITÉS qui a été débitée du stock_global
            $table->integer('quantite_unites')
                  ->nullable()
                  ->after('quantite');

            // Totaux ligne (HT / TTC)
            $table->integer('total_ht')->nullable()->after('prix_unitaire');
            $table->integer('total_ttc')->nullable()->after('total_ht');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detail_commandes', function (Blueprint $table) {
            $table->dropColumn([
                'libelle',
                'mode_vente',
                'quantite_unites',
                'total_ht',
                'total_ttc',
            ]);
        });
    }
};
