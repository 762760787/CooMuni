<?php

namespace App\Models;

use App\Enums\StatutMembre;
use App\Models\Concerns\InterditSuppression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembreStatut extends Model
{
    use InterditSuppression;

    protected $fillable = ['membre_id', 'ancien_statut', 'nouveau_statut', 'date_effet', 'motif', 'user_id'];

    protected function casts(): array
    {
        return [
            'date_effet' => 'date',
            'ancien_statut' => StatutMembre::class,
            'nouveau_statut' => StatutMembre::class,
        ];
    }

    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
