<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Les montants sont stockés en FCFA entiers (le franc CFA n'a pas de
     * subdivision utilisée) : aucun arrondi flottant possible.
     */
    public function up(): void
    {
        // Situation attendue de chaque membre pour chaque période (§16 « cotisations »).
        Schema::create('cotisations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membre_id')->constrained('membres')->restrictOnDelete();
            $table->char('periode', 7); // AAAA-MM
            $table->unsignedSmallInteger('annee');
            $table->unsignedTinyInteger('mois');
            $table->unsignedBigInteger('montant_attendu');
            $table->unsignedBigInteger('montant_paye')->default(0);
            $table->date('date_echeance');
            $table->date('date_paiement')->nullable(); // date du règlement ayant soldé (ou du dernier règlement)
            $table->enum('statut', ['a_payer', 'partiel', 'paye', 'paye_retard', 'annule', 'regularise'])->default('a_payer');
            $table->text('motif')->nullable(); // motif d'annulation / de régularisation
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traite_le')->nullable();
            $table->timestamps();

            $table->unique(['membre_id', 'periode']);
            $table->index(['periode', 'statut']);
            $table->index(['statut', 'date_echeance']);
            $table->index(['annee', 'mois']);
        });

        // Opération de règlement = une transaction = un reçu (§16 « paiements »).
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->string('numero_recu', 30)->unique();
            $table->foreignId('membre_id')->constrained('membres')->restrictOnDelete();
            $table->unsignedBigInteger('montant');
            $table->date('date_paiement');
            $table->foreignId('mode_paiement_id')->constrained('modes_paiement')->restrictOnDelete();
            $table->string('reference', 100)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('enregistre_par')->constrained('users')->restrictOnDelete();
            $table->enum('statut', ['valide', 'annule'])->default('valide');
            // Paiement qu'il remplace (workflow de correction : annulation + nouvelle saisie).
            $table->foreignId('corrige_paiement_id')->nullable()->constrained('paiements')->nullOnDelete();
            // Workflow d'annulation (demande par un gestionnaire, validation par un administrateur).
            $table->text('demande_annulation_motif')->nullable();
            $table->foreignId('demande_annulation_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('demande_annulation_le')->nullable();
            $table->text('motif_annulation')->nullable();
            $table->foreignId('annule_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('annule_le')->nullable();
            $table->timestamps();

            $table->index(['membre_id', 'date_paiement']);
            $table->index(['statut', 'date_paiement']);
            $table->index('reference');
        });

        // Ventilation d'un paiement sur une ou plusieurs cotisations (paiement groupé / partiel).
        Schema::create('paiement_cotisation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paiement_id')->constrained('paiements')->restrictOnDelete();
            $table->foreignId('cotisation_id')->constrained('cotisations')->restrictOnDelete();
            $table->unsignedBigInteger('montant');
            $table->timestamps();

            $table->unique(['paiement_id', 'cotisation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiement_cotisation');
        Schema::dropIfExists('paiements');
        Schema::dropIfExists('cotisations');
    }
};
