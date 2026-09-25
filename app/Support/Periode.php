<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Stringable;

/**
 * Période mensuelle de cotisation au format AAAA-MM.
 */
final class Periode implements Stringable
{
    private const MOIS = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
        7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
    ];

    public function __construct(public readonly int $annee, public readonly int $mois)
    {
        if ($mois < 1 || $mois > 12 || $annee < 2000 || $annee > 2100) {
            throw new InvalidArgumentException("Période invalide : {$annee}-{$mois}");
        }
    }

    public static function fromString(string $periode): self
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $periode, $m)) {
            throw new InvalidArgumentException("Période invalide : {$periode}");
        }

        return new self((int) $m[1], (int) $m[2]);
    }

    public static function isValid(?string $periode): bool
    {
        if ($periode === null) {
            return false;
        }
        try {
            self::fromString($periode);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public static function fromDate(\DateTimeInterface $date): self
    {
        return new self((int) $date->format('Y'), (int) $date->format('n'));
    }

    public static function courante(): self
    {
        return self::fromDate(now());
    }

    /** @return list<self> Périodes de $debut à $fin incluses. */
    public static function plage(self $debut, self $fin): array
    {
        $out = [];
        for ($p = $debut; $p->compare($fin) <= 0; $p = $p->suivante()) {
            $out[] = $p;
        }

        return $out;
    }

    public function suivante(): self
    {
        return $this->mois === 12 ? new self($this->annee + 1, 1) : new self($this->annee, $this->mois + 1);
    }

    public function precedente(): self
    {
        return $this->mois === 1 ? new self($this->annee - 1, 12) : new self($this->annee, $this->mois - 1);
    }

    public function ajouterMois(int $n): self
    {
        $p = $this;
        for ($i = 0; $i < abs($n); $i++) {
            $p = $n > 0 ? $p->suivante() : $p->precedente();
        }

        return $p;
    }

    public function compare(self $autre): int
    {
        return [$this->annee, $this->mois] <=> [$autre->annee, $autre->mois];
    }

    public function premierJour(): CarbonImmutable
    {
        return CarbonImmutable::create($this->annee, $this->mois, 1)->startOfDay();
    }

    public function dernierJour(): CarbonImmutable
    {
        return $this->premierJour()->endOfMonth()->startOfDay();
    }

    /** Date d'échéance : le jour paramétré, borné à la longueur du mois. */
    public function echeance(int $jour): CarbonImmutable
    {
        $jour = max(1, min($jour, $this->premierJour()->daysInMonth));

        return CarbonImmutable::create($this->annee, $this->mois, $jour)->startOfDay();
    }

    public function libelle(): string
    {
        return self::MOIS[$this->mois].' '.$this->annee;
    }

    public function libelleCourt(): string
    {
        return self::abreviation($this->mois).' '.substr((string) $this->annee, 2);
    }

    /** Abréviation usuelle (Janv., Juin, Juil., Sept.…) sans ambiguïté juin / juillet. */
    public static function abreviation(int $mois): string
    {
        return ['', 'Janv.', 'Févr.', 'Mars', 'Avr.', 'Mai', 'Juin', 'Juil.', 'Août', 'Sept.', 'Oct.', 'Nov.', 'Déc.'][$mois];
    }

    public static function nomMois(int $mois): string
    {
        return self::MOIS[$mois];
    }

    public function __toString(): string
    {
        return sprintf('%04d-%02d', $this->annee, $this->mois);
    }
}
