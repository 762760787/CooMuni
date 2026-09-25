<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Comptes utilisateurs (cahier des charges §16 « users »).
        // Le rôle est porté par spatie/laravel-permission (table model_has_roles)
        // plutôt que par une colonne role_id : voir ASSUMPTIONS.md.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Identifiant de connexion : nom d'utilisateur (ex. matricule pour un membre).
            // La connexion accepte aussi l'email ou le téléphone.
            $table->string('identifiant', 60)->unique();
            $table->string('email')->nullable()->unique();
            $table->string('telephone', 30)->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            // Lien vers la fiche membre (contrainte ajoutée après création de `membres`).
            $table->unsignedBigInteger('membre_id')->nullable()->unique();
            $table->boolean('actif')->default(true);
            $table->boolean('doit_changer_mdp')->default(false);
            $table->timestamp('derniere_connexion_at')->nullable();
            $table->json('preferences')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('actif');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
