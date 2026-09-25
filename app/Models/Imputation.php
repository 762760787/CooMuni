<?php

namespace App\Models;

use App\Models\Concerns\InterditSuppression;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Ventilation d'un paiement sur une cotisation (table paiement_cotisation).
 */
class Imputation extends Pivot
{
    use InterditSuppression;

    protected $table = 'paiement_cotisation';

    public $incrementing = true;

    protected $fillable = ['paiement_id', 'cotisation_id', 'montant'];

    protected function casts(): array
    {
        return ['montant' => 'integer'];
    }

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }

    public function cotisation(): BelongsTo
    {
        return $this->belongsTo(Cotisation::class);
    }
}
