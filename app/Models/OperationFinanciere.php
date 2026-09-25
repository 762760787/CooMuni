<?php

namespace App\Models;

use App\Models\Concerns\InterditSuppression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationFinanciere extends Model
{
    use InterditSuppression;

    protected $table = 'operations_financieres';

    protected $fillable = [
        'numero', 'type', 'categorie_id', 'montant', 'date_operation', 'description', 'reference',
        'mode_paiement_id', 'justificatif', 'justificatif_nom', 'enregistre_par', 'statut',
        'demande_annulation_motif', 'demande_annulation_par', 'demande_annulation_le',
        'motif_annulation', 'annule_par', 'annule_le',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'date_operation' => 'date',
            'demande_annulation_le' => 'datetime',
            'annule_le' => 'datetime',
        ];
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class);
    }

    public function modePaiement(): BelongsTo
    {
        return $this->belongsTo(ModePaiement::class);
    }

    public function enregistrePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enregistre_par');
    }

    public function annulePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annule_par');
    }

    public function demandeAnnulationPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demande_annulation_par');
    }

    public function estAnnule(): bool
    {
        return $this->statut === 'annule';
    }

    public function annulationEnAttente(): bool
    {
        return ! $this->estAnnule() && $this->demande_annulation_le !== null;
    }

    public function estEntree(): bool
    {
        return $this->type === 'entree';
    }

    public function scopeValides(Builder $q): Builder
    {
        return $q->where('statut', 'valide');
    }
}
