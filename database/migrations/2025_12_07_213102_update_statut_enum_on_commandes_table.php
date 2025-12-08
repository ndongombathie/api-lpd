<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE commandes 
            MODIFY statut ENUM(
                'brouillon',
                'validee',
                'payee',
                'annulee',
                'en_attente_caisse',
                'partiellement_payee',
                'soldee'
            ) DEFAULT 'en_attente_caisse'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE commandes 
            MODIFY statut ENUM(
                'brouillon',
                'validee',
                'payee',
                'annulee'
            ) DEFAULT 'brouillon'
        ");
    }
};
