<?php

namespace App\Models;

use App\Models\Concerns\InterditSuppression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Catégorie financière : désactivable, jamais supprimée (historique des opérations). */
class Categorie extends Model
{
    use InterditSuppression;

    protected $fillable = ['nom', 'type', 'description', 'restreinte', 'actif'];

    protected function casts(): array
    {
        return ['restreinte' => 'boolean', 'actif' => 'boolean'];
    }

    public function operations(): HasMany
    {
        return $this->hasMany(OperationFinanciere::class);
    }

    public function scopeActives(Builder $q): Builder
    {
        return $q->where('actif', true);
    }
}
