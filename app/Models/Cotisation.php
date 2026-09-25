<?php

namespace App\Models;

use App\Enums\EtatCotisation;
use App\Enums\StatutCotisation;
use App\Models\Concerns\InterditSuppression;
use App\Support\Periode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cotisation extends Model
{
    use InterditSuppression;

    protected $fillable = [
        'membre_id', 'periode', 'annee', 'mois', 'montant_attendu', 'montant_paye',
        'date_echeance', 'date_paiement', 'statut', 'motif', 'traite_par', 'traite_le',
    ];

    protected function casts(): array
    {
        return [
            'montant_attendu' => 'integer',
            'montant_paye' => 'integer',
            'date_echeance' => 'date',
            'date_paiement' => 'date',
            'traite_le' => 'datetime',
            'statut' => StatutCotisation::class,
        ];
    }

    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    public function imputations(): HasMany
    {
        return $this->hasMany(Imputation::class);
    }

    public function paiements(): BelongsToMany
    {
        return $this->belongsToMany(Paiement::class, 'paiement_cotisation')
            ->using(Imputation::class)
            ->withPivot('montant')
            ->withTimestamps();
    }

    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par');
    }

    public function periodeObjet(): Periode
    {
        return Periode::fromString($this->periode);
    }

    public function getPeriodeLibelleAttribute(): string
    {
        return $this->periodeObjet()->libelle();
    }

    public function getResteAttribute(): int
    {
        return $this->statut->estDue() ? max(0, $this->montant_attendu - $this->montant_paye) : 0;
    }

    public function estEchue(?\DateTimeInterface $aujourdhui = null): bool
    {
        $aujourdhui ??= now();

        return $this->date_echeance->lt($aujourdhui->format('Y-m-d'));
    }

    public function joursRetard(): ?int
    {
        if ($this->statut === StatutCotisation::PayeRetard && $this->date_paiement) {
            return (int) $this->date_echeance->diffInDays($this->date_paiement);
        }
        if ($this->statut->estDue() && $this->estEchue()) {
            return (int) $this->date_echeance->diffInDays(today());
        }

        return null;
    }

    public function etat(): EtatCotisation
    {
        return match ($this->statut) {
            StatutCotisation::APayer => $this->estEchue() ? EtatCotisation::Impaye : EtatCotisation::AVenir,
            StatutCotisation::Partiel => $this->estEchue() ? EtatCotisation::PartielRetard : EtatCotisation::Partiel,
            StatutCotisation::Paye => EtatCotisation::Paye,
            StatutCotisation::PayeRetard => EtatCotisation::PayeRetard,
            StatutCotisation::Annule => EtatCotisation::Annule,
            StatutCotisation::Regularise => EtatCotisation::Regularise,
        };
    }

    /** Cotisations non soldées dont l'échéance est dépassée (= impayés). */
    public function scopeImpayees(Builder $q): Builder
    {
        return $q->whereIn($q->qualifyColumn('statut'), StatutCotisation::dues())
            ->whereDate($q->qualifyColumn('date_echeance'), '<', today());
    }

    public function scopeDues(Builder $q): Builder
    {
        return $q->whereIn($q->qualifyColumn('statut'), StatutCotisation::dues());
    }

    /** Cotisations prises en compte dans les montants attendus (hors annulées). */
    public function scopeExigibles(Builder $q): Builder
    {
        return $q->where($q->qualifyColumn('statut'), '!=', StatutCotisation::Annule->value);
    }
}
