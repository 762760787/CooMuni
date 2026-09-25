<?php

namespace App\Models;

use App\Enums\StatutMembre;
use App\Models\Concerns\InterditSuppression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Membre extends Model
{
    use InterditSuppression;

    protected $fillable = [
        'matricule', 'nom', 'prenom', 'sexe', 'telephone', 'email', 'fonction', 'service',
        'date_adhesion', 'date_sortie', 'statut', 'photo', 'observations', 'cree_par',
    ];

    protected function casts(): array
    {
        return [
            'date_adhesion' => 'date',
            'date_sortie' => 'date',
            'statut' => StatutMembre::class,
        ];
    }

    public function cotisations(): HasMany
    {
        return $this->hasMany(Cotisation::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function historiqueStatuts(): HasMany
    {
        return $this->hasMany(MembreStatut::class)->latest('id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function getNomCompletAttribute(): string
    {
        return trim($this->prenom.' '.$this->nom);
    }

    public function initiales(): string
    {
        return mb_strtoupper(mb_substr($this->prenom, 0, 1).mb_substr($this->nom, 0, 1));
    }

    /** Recherche globale : nom, prénom, matricule, téléphone (§7.11). */
    public function scopeRecherche(Builder $q, ?string $terme): Builder
    {
        $terme = trim((string) $terme);
        if ($terme === '') {
            return $q;
        }

        return $q->where(function (Builder $q) use ($terme) {
            foreach (preg_split('/\s+/', $terme) as $mot) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $mot).'%';
                $q->where(fn (Builder $q) => $q
                    ->where('nom', 'like', $like)
                    ->orWhere('prenom', 'like', $like)
                    ->orWhere('matricule', 'like', $like)
                    ->orWhere('telephone', 'like', $like));
            }
        });
    }

    public function scopeActifs(Builder $q): Builder
    {
        return $q->where('statut', StatutMembre::Actif->value);
    }
}
