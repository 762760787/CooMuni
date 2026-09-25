<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notifications internes, extensibles à d'autres canaux (§7.9, §18).
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 60); // rappel_echeance, confirmation_paiement, information, ...
            $table->enum('canal', ['interne', 'email', 'sms', 'whatsapp'])->default('interne');
            $table->string('titre');
            $table->text('contenu');
            $table->string('lien')->nullable();
            $table->boolean('lu')->default(false);
            $table->timestamp('lu_le')->nullable();
            $table->string('cle_unicite', 120)->nullable(); // évite l'envoi en double d'un même rappel
            $table->timestamps();

            $table->index(['user_id', 'lu']);
            $table->unique(['user_id', 'cle_unicite']);
        });

        // Journal d'audit — en ajout seul, jamais modifié ni supprimé (§8.1, §13, §16 « audit_logs »).
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('cible_type', 80)->nullable();
            $table->unsignedBigInteger('cible_id')->nullable();
            $table->string('description')->nullable();
            $table->json('ancienne_valeur')->nullable();
            $table->json('nouvelle_valeur')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('date_action')->useCurrent();

            $table->index(['action', 'date_action']);
            $table->index(['cible_type', 'cible_id']);
            $table->index(['user_id', 'date_action']);
            $table->index('date_action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
    }
};
