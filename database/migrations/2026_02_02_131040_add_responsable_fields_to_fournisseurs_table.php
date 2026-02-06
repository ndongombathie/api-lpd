<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('fournisseurs', function (Blueprint $table) {
            $table->string('type_produit')->nullable();
            $table->date('derniere_livraison')->nullable();
        });
    }

    public function down()
    {
        Schema::table('fournisseurs', function (Blueprint $table) {
            $table->dropColumn(['type_produit', 'derniere_livraison']);
        });
    }

};
