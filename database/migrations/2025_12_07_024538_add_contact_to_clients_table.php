<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // On ajoute la colonne seulement si elle n'existe pas déjà
        if (!Schema::hasColumn('clients', 'contact')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('contact')->nullable()->after('telephone');
            });
        }
    }

    public function down(): void
    {
        // On la supprime seulement si elle existe
        if (Schema::hasColumn('clients', 'contact')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('contact');
            });
        }
    }
};
