<?php

use App\Services\Parametres;
use App\Support\Periode;

if (! function_exists('fcfa')) {
    /** Formate un montant entier en FCFA : 10 000 FCFA. */
    function fcfa(int|float|string|null $montant, bool $devise = true): string
    {
        $txt = number_format((float) ($montant ?? 0), 0, ',', "\u{202F}");

        return $devise ? $txt."\u{00A0}FCFA" : $txt;
    }
}

if (! function_exists('periode_libelle')) {
    function periode_libelle(?string $periode): string
    {
        return Periode::isValid($periode) ? Periode::fromString($periode)->libelle() : (string) $periode;
    }
}

if (! function_exists('parametre')) {
    function parametre(string $cle, mixed $defaut = null): mixed
    {
        return app(Parametres::class)->get($cle, $defaut);
    }
}

if (! function_exists('date_fr')) {
    function date_fr(\DateTimeInterface|string|null $date, string $format = 'd/m/Y'): string
    {
        if ($date === null || $date === '') {
            return '—';
        }

        return \Illuminate\Support\Carbon::parse($date)->format($format);
    }
}
