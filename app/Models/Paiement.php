<?php

namespace App\Models;

use App\Models\Concerns\InterditSuppression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Paiement extends Model
{
    use InterditSuppression;

    protected $fillable = [
        'numero_recu', 'membre_id', 'montant', 'date_paiement', 'mode_paiement_id', 'reference', 'note',
        'enregistre_par', 'statut', 'corrige_paiement_id',
        'demande_annulation_motif', 'demande_annulation_par', 'demande_annulation_le',
        'motif_annulation', 'annule_par', 'annule_le',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'date_paiement' => 'date',
            'demande_annulation_le' => 'datetime',
            'annule_le' => 'datetime',
        ];
    }

    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
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

    public function paiementCorrige(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrige_paiement_id');
    }

    public function correction(): HasOne
    {
        return $this->hasOne(self::class, 'corrige_paiement_id');
    }

    public function imputations(): HasMany
    {
        return $this->hasMany(Imputation::class);
    }

    public function cotisations(): BelongsToMany
    {
        return $this->belongsToMany(Cotisation::class, 'paiement_cotisation')
            ->using(Imputation::class)
            ->withPivot('montant')
            ->withTimestamps()
            ->orderBy('periode');
    }

    public function estAnnule(): bool
    {
        return $this->statut === 'annule';
    }

    public function annulationEnAttente(): bool
    {
        return ! $this->estAnnule() && $this->demande_annulation_le !== null;
    }

    public function libellePeriodes(): string
    {
        $periodes = $this->cotisations->pluck('periode')->map(fn ($p) => periode_libelle($p));

        return $periodes->count() > 3
            ? $periodes->first().' → '.$periodes->last().' ('.$periodes->count().' mois)'
            : $periodes->implode(', ');
    }

    public function scopeValides(Builder $q): Builder
    {
        return $q->where('statut', 'valide');
    }

    public function scopeEnAttenteAnnulation(Builder $q): Builder
    {
        return $q->where('statut', 'valide')->whereNotNull('demande_annulation_le');
    }
}
