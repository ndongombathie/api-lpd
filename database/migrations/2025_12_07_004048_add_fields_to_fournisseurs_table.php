<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fournisseurs', function (Blueprint $table) {
            // 🔗 Lier à la boutique (même logique que produits, etc.)
            $table->uuid('boutique_id')
                  ->after('id');

            // si tu veux la contrainte FK (facultatif si tu galères avec les FK)
            $table->foreign('boutique_id')
                  ->references('id')
                  ->on('boutiques')
                  ->onDelete('cascade');

            // 🌐 Type de produits (Papeterie, Stylos…)
            $table->string('type_produit')
                  ->nullable()
                  ->after('adresse');

            // 📅 Dernière livraison
            $table->date('derniere_livraison')
                  ->nullable()
                  ->after('type_produit');
        });
    }

    public function down(): void
    {
        Schema::table('fournisseurs', function (Blueprint $table) {
            // rollback propre
            $table->dropForeign(['boutique_id']);
            $table->dropColumn([
                'boutique_id',
                'type_produit',
                'derniere_livraison',
            ]);
        });
    }
};
