<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Règle §8.1 « Non-suppression définitive » : les données financières et
 * les référentiels historisés ne sont jamais supprimés. Toute tentative
 * lève une exception : il faut passer par une annulation tracée.
 */
trait InterditSuppression
{
    public static function bootInterditSuppression(): void
    {
        static::deleting(function ($model) {
            throw new LogicException(
                'Suppression interdite pour '.class_basename($model).' : utilisez une annulation tracée.'
            );
        });
    }
}
