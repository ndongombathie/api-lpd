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
        Schema::table('users', function (Blueprint $table) {
            $table->string('telephone_hash')->nullable()->index();
            $table->string('numero_cni_hash')->nullable()->index();
            $table->string('email_hash')->nullable()->index();
            $table->string('adresse_hash')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('telephone_hash');
            $table->dropColumn('numero_cni_hash');
            $table->dropColumn('email_hash');
            $table->dropColumn('adresse_hash');
        });
    }
};
