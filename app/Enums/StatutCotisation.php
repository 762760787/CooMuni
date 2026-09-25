<?php

namespace App\Enums;

/**
 * Statut stocké d'une cotisation (§7.2).
 * « Impayé » n'est pas un statut stocké : c'est une cotisation « À payer » ou
 * « Partiellement payé » dont l'échéance est dépassée (voir EtatCotisation).
 */
enum StatutCotisation: string
{
    case APayer = 'a_payer';
    case Partiel = 'partiel';
    case Paye = 'paye';
    case PayeRetard = 'paye_retard';
    case Annule = 'annule';
    case Regularise = 'regularise';

    public function label(): string
    {
        return match ($this) {
            self::APayer => 'À payer',
            self::Partiel => 'Partiellement payé',
            self::Paye => 'Payé',
            self::PayeRetard => 'Payé en retard',
            self::Annule => 'Annulé',
            self::Regularise => 'Régularisé',
        };
    }

    /** Cotisation encore due (tout ou partie). */
    public function estDue(): bool
    {
        return in_array($this, [self::APayer, self::Partiel], true);
    }

    /** Cotisation soldée par un paiement. */
    public function estReglee(): bool
    {
        return in_array($this, [self::Paye, self::PayeRetard], true);
    }

    /** @return list<string> */
    public static function dues(): array
    {
        return [self::APayer->value, self::Partiel->value];
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
