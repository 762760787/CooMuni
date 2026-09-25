<?php

namespace App\Enums;

enum StatutMembre: string
{
    case Actif = 'actif';
    case Suspendu = 'suspendu';
    case Sorti = 'sorti';
    case Decede = 'decede';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Actif => 'Actif',
            self::Suspendu => 'Suspendu',
            self::Sorti => 'Sorti',
            self::Decede => 'Décédé',
            self::Autre => 'Autre',
        };
    }

    public function couleur(): string
    {
        return match ($this) {
            self::Actif => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::Suspendu => 'bg-amber-100 text-amber-800 ring-amber-600/20',
            self::Sorti => 'bg-slate-100 text-slate-700 ring-slate-500/20',
            self::Decede => 'bg-zinc-200 text-zinc-800 ring-zinc-600/20',
            self::Autre => 'bg-sky-100 text-sky-800 ring-sky-600/20',
        };
    }

    /** Statuts qui mettent fin à l'adhésion (date de sortie obligatoire). */
    public function estDefinitif(): bool
    {
        return in_array($this, [self::Sorti, self::Decede], true);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
