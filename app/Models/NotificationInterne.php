<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Notification (table `notifications`, §16) — canal interne en V1. */
class NotificationInterne extends Model
{
    protected $table = 'notifications';

    protected $fillable = ['user_id', 'type', 'canal', 'titre', 'contenu', 'lien', 'lu', 'lu_le', 'cle_unicite'];

    protected function casts(): array
    {
        return ['lu' => 'boolean', 'lu_le' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeNonLues(Builder $q): Builder
    {
        return $q->where('lu', false);
    }

    public function icone(): string
    {
        return match ($this->type) {
            'confirmation_paiement' => '✓',
            'rappel_echeance', 'rappel_jour_echeance' => '⏰',
            'retard' => '!',
            'demande_annulation', 'demande_reinitialisation' => '⚑',
            default => 'i',
        };
    }
}
