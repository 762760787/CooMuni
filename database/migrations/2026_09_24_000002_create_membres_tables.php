<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Référentiel des membres (§7.1, §16 « membres »).
        Schema::create('membres', function (Blueprint $table) {
            $table->id();
            $table->string('matricule', 30)->unique();
            $table->string('nom', 100);
            $table->string('prenom', 150);
            $table->enum('sexe', ['H', 'F'])->nullable();
            $table->string('telephone', 30)->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('fonction', 120)->nullable();
            $table->string('service', 120)->nullable();
            $table->date('date_adhesion');
            $table->date('date_sortie')->nullable(); // date d'effet d'une sortie ou d'un décès
            $table->enum('statut', ['actif', 'suspendu', 'sorti', 'decede', 'autre'])->default('actif');
            $table->string('photo')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['nom', 'prenom']);
            $table->index('statut');
            $table->index('service');
        });

        // Historique des changements de statut (§7.1).
        Schema::create('membre_statuts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membre_id')->constrained('membres')->restrictOnDelete();
            $table->string('ancien_statut', 20)->nullable();
            $table->string('nouveau_statut', 20);
            $table->date('date_effet');
            $table->text('motif')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('membre_id')->references('id')->on('membres')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['membre_id']);
        });
        Schema::dropIfExists('membre_statuts');
        Schema::dropIfExists('membres');
    }
};
