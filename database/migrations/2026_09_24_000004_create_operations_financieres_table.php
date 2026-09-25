<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mouvements de caisse hors cotisations (§7.8, §16 « operations_financieres »).
        Schema::create('operations_financieres', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->enum('type', ['entree', 'sortie']);
            $table->foreignId('categorie_id')->constrained('categories')->restrictOnDelete();
            $table->unsignedBigInteger('montant');
            $table->date('date_operation');
            $table->text('description');
            $table->string('reference', 100)->nullable();
            $table->foreignId('mode_paiement_id')->nullable()->constrained('modes_paiement')->nullOnDelete();
            $table->string('justificatif')->nullable();      // chemin sur le disque privé
            $table->string('justificatif_nom')->nullable();  // nom d'origine du fichier
            $table->foreignId('enregistre_par')->constrained('users')->restrictOnDelete();
            $table->enum('statut', ['valide', 'annule'])->default('valide');
            $table->text('demande_annulation_motif')->nullable();
            $table->foreignId('demande_annulation_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('demande_annulation_le')->nullable();
            $table->text('motif_annulation')->nullable();
            $table->foreignId('annule_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('annule_le')->nullable();
            $table->timestamps();

            $table->index(['statut', 'date_operation']);
            $table->index(['type', 'date_operation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_financieres');
    }
};
