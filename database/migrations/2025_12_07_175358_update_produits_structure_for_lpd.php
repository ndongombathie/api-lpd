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
        Schema::table('produits', function (Blueprint $table) {
            // 🔁 Renommer les anciens champs pour coller à la nouvelle logique
            if (Schema::hasColumn('produits', 'code')) {
                $table->renameColumn('code', 'code_barre');
            }

            if (Schema::hasColumn('produits', 'prix_vente')) {
                $table->renameColumn('prix_vente', 'prix_basique_detail');
            }

            if (Schema::hasColumn('produits', 'prix_gros')) {
                $table->renameColumn('prix_gros', 'prix_basique_gros');
            }

            if (Schema::hasColumn('produits', 'prix_seuil')) {
                $table->renameColumn('prix_seuil', 'prix_seuil_detail');
            }

            // 🧹 On enlève l'ancien "prix_achat" qui ne correspond plus à notre logique
            if (Schema::hasColumn('produits', 'prix_achat')) {
                $table->dropColumn('prix_achat');
            }

            // ➕ Nouveaux champs pour la logique LPD
            if (!Schema::hasColumn('produits', 'nombre_cartons')) {
                $table->integer('nombre_cartons')->default(0)->after('categorie');
            }

            if (!Schema::hasColumn('produits', 'unites_par_carton')) {
                $table->integer('unites_par_carton')->default(1)->after('nombre_cartons');
            }

            if (!Schema::hasColumn('produits', 'prix_seuil_gros')) {
                $table->integer('prix_seuil_gros')->nullable()->after('prix_basique_gros');
            }

            if (!Schema::hasColumn('produits', 'quantite_seuil')) {
                $table->integer('quantite_seuil')->nullable()->after('prix_seuil_gros');
            }

            // ⚠️ On garde `stock_global` tel quel (déjà présent dans ta table)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            // On annule les ajouts
            if (Schema::hasColumn('produits', 'quantite_seuil')) {
                $table->dropColumn('quantite_seuil');
            }

            if (Schema::hasColumn('produits', 'prix_seuil_gros')) {
                $table->dropColumn('prix_seuil_gros');
            }

            if (Schema::hasColumn('produits', 'unites_par_carton')) {
                $table->dropColumn('unites_par_carton');
            }

            if (Schema::hasColumn('produits', 'nombre_cartons')) {
                $table->dropColumn('nombre_cartons');
            }

            // On remet les anciens noms
            if (Schema::hasColumn('produits', 'prix_seuil_detail')) {
                $table->renameColumn('prix_seuil_detail', 'prix_seuil');
            }

            if (Schema::hasColumn('produits', 'prix_basique_gros')) {
                $table->renameColumn('prix_basique_gros', 'prix_gros');
            }

            if (Schema::hasColumn('produits', 'prix_basique_detail')) {
                $table->renameColumn('prix_basique_detail', 'prix_vente');
            }

            if (Schema::hasColumn('produits', 'code_barre')) {
                $table->renameColumn('code_barre', 'code');
            }

            // On remet prix_achat si besoin
            if (!Schema::hasColumn('produits', 'prix_achat')) {
                $table->integer('prix_achat')->nullable()->after('prix_vente');
            }
        });
    }
};
