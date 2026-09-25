<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Paramètres métier (§7.12, §8.1) : jamais codés en dur.
        Schema::create('parametres', function (Blueprint $table) {
            $table->id();
            $table->string('cle', 80)->unique();
            $table->text('valeur')->nullable();
            $table->string('type', 20)->default('string'); // string, int, bool, month, text, image, select
            $table->string('groupe', 40)->default('general');
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->json('options')->nullable(); // choix possibles pour le type « select »
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });

        // Modes de paiement configurables (§7.7).
        Schema::create('modes_paiement', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 80)->unique();
            $table->string('code', 40)->unique();
            $table->boolean('reference_requise')->default(false);
            $table->boolean('actif')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });

        // Catégories financières configurables (§7.8).
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 120);
            $table->enum('type', ['entree', 'sortie']);
            $table->string('description')->nullable();
            // Catégorie réservée : saisie soumise à la permission operations.categories_restreintes.
            $table->boolean('restreinte')->default(false);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['nom', 'type']);
        });

        // Compteurs de numérotation (reçus, opérations) — verrouillés en transaction.
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 40);
            $table->unsignedSmallInteger('annee');
            $table->unsignedBigInteger('valeur')->default(0);
            $table->timestamps();

            $table->unique(['nom', 'annee']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('modes_paiement');
        Schema::dropIfExists('parametres');
    }
};
