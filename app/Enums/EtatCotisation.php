<?php

namespace App\Enums;

/**
 * État affiché d'une cotisation, dérivé du statut stocké et de la date du jour :
 * c'est lui qui porte la distinction « à payer / impayé » (§8.1).
 */
enum EtatCotisation: string
{
    case AVenir = 'a_venir';
    case Impaye = 'impaye';
    case Partiel = 'partiel';
    case PartielRetard = 'partiel_retard';
    case Paye = 'paye';
    case PayeRetard = 'paye_retard';
    case Annule = 'annule';
    case Regularise = 'regularise';

    public function label(): string
    {
        return match ($this) {
            self::AVenir => 'À payer',
            self::Impaye => 'Impayé',
            self::Partiel => 'Partiellement payé',
            self::PartielRetard => 'Partiel · impayé',
            self::Paye => 'Payé',
            self::PayeRetard => 'Payé en retard',
            self::Annule => 'Annulé',
            self::Regularise => 'Régularisé',
        };
    }

    public function couleur(): string
    {
        return match ($this) {
            self::AVenir => 'bg-sky-100 text-sky-800 ring-sky-600/20',
            self::Impaye => 'bg-red-100 text-red-800 ring-red-600/20',
            self::Partiel => 'bg-violet-100 text-violet-800 ring-violet-600/20',
            self::PartielRetard => 'bg-rose-100 text-rose-800 ring-rose-600/20',
            self::Paye => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::PayeRetard => 'bg-amber-100 text-amber-900 ring-amber-600/30',
            self::Annule => 'bg-slate-100 text-slate-600 ring-slate-500/20',
            self::Regularise => 'bg-teal-100 text-teal-800 ring-teal-600/20',
        };
    }

    /** Couleur « pleine » pour les pastilles du calendrier mensuel. */
    public function pastille(): string
    {
        return match ($this) {
            self::AVenir => 'bg-sky-500',
            self::Impaye => 'bg-red-600',
            self::Partiel => 'bg-violet-500',
            self::PartielRetard => 'bg-rose-500',
            self::Paye => 'bg-emerald-600',
            self::PayeRetard => 'bg-amber-500',
            self::Annule => 'bg-slate-300',
            self::Regularise => 'bg-teal-500',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::Paye, self::Regularise => '✓',
            self::PayeRetard => '⏱',
            self::Impaye, self::PartielRetard => '!',
            self::Partiel => '½',
            self::AVenir => '•',
            self::Annule => '–',
        };
    }
}
