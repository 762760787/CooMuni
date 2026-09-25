<?php

namespace App\Models;

use App\Models\Concerns\InterditSuppression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Mode de paiement : désactivable, jamais supprimé (référencé par les paiements). */
class ModePaiement extends Model
{
    use InterditSuppression;

    protected $table = 'modes_paiement';

    protected $fillable = ['nom', 'code', 'reference_requise', 'actif', 'ordre'];

    protected function casts(): array
    {
        return ['reference_requise' => 'boolean', 'actif' => 'boolean', 'ordre' => 'integer'];
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function scopeActifs(Builder $q): Builder
    {
        return $q->where('actif', true)->orderBy('ordre')->orderBy('nom');
    }
}
