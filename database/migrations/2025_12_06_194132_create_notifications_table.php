<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // 🔗 Référence propre vers users.id (UUID)
            $table->foreignUuid('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            // 👉 Colonnes métier alignées sur ton Model
            $table->string('module', 100);    // ex: 'inventaire', 'rapports', 'decaissements', etc.
            $table->string('type', 50);       // ex: 'info', 'warning', 'error', 'success'
            $table->string('title');          // petit titre
            $table->text('message')->nullable(); // texte détaillé

            $table->string('url')->nullable();   // ex: '/responsable/rapports'
            $table->json('data')->nullable();    // infos supplémentaires (id commande, etc.)

            $table->timestamp('read_at')->nullable(); // null = non lue

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
