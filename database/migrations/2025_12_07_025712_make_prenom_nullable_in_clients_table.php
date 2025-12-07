<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // on autorise NULL pour les clients spéciaux
            $table->string('prenom')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // retour en NOT NULL si jamais tu rollback
            $table->string('prenom')->nullable(false)->change();
        });
    }
};
