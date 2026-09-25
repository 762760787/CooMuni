<?php

namespace App\Services\Assistant\Local;

use Illuminate\Support\Str;

/**
 * Outils de texte du moteur local : normalisation (accents, casse, ponctuation),
 * découpage en mots et comparaison approchée des noms, qui tient compte des
 * orthographes française et wolof (Ndiaye / Njaay, Diouf / Juuf, Sène / Seen…).
 */
final class Texte
{
    public static function normaliser(string $texte): string
    {
        $texte = str_replace(['’', "'", '`', '´', 'ʼ'], ' ', $texte);
        $texte = mb_strtolower(Str::ascii($texte));
        $texte = preg_replace('/[^a-z0-9\/\-\.\s,:?#+]/', ' ', $texte);
        // Trait d'union entre lettres = espace (« avant-hier », « vingt-cinq ») ; conservé entre chiffres (dates, matricules).
        $texte = preg_replace('/(?<=[a-z])-(?=[a-z])/', ' ', $texte);

        return trim(preg_replace('/\s+/', ' ', $texte));
    }

    /** @return list<string> */
    public static function mots(string $texteNormalise): array
    {
        preg_match_all('/[a-z0-9]+/', $texteNormalise, $m);

        return $m[0];
    }

    /**
     * Clé phonétique simplifiée, identique pour les variantes d'écriture courantes au Sénégal.
     */
    public static function phonetique(string $mot): string
    {
        $m = Lexique::ALIAS_NOMS[$mot] ?? $mot;
        $m = strtr($m, ['ph' => 'f', 'th' => 't', 'dj' => 'j', 'sh' => 's', 'ch' => 's', 'ou' => 'u', 'kh' => 'k', 'gn' => 'n', 'ny' => 'n', 'x' => 'k', 'q' => 'k', 'w' => 'u']);
        $m = preg_replace('/c(?=[eiy])/', 's', $m);
        $m = preg_replace('/gu(?=[eiy])/', 'g', $m);
        $m = preg_replace('/di(?=[aeou])/', 'j', $m);
        $m = str_replace(['y', 'h'], ['i', ''], $m);
        $m = preg_replace('/(.)\1+/', '$1', $m);
        if (strlen($m) > 3) {
            $m = preg_replace('/e$/', '', $m);
        }

        return $m;
    }

    /** Degré de ressemblance de deux mots normalisés (0 = différents, 1 = identiques). */
    public static function ressemblance(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }
        if (strlen($a) < 2 || strlen($b) < 2) {
            return 0.0;
        }
        $ka = self::phonetique($a);
        $kb = self::phonetique($b);
        if ($ka === $kb) {
            return 0.9;
        }
        $longueur = max(strlen($ka), strlen($kb));
        if (min(strlen($ka), strlen($kb)) < 3) {
            return 0.0;
        }
        $distance = levenshtein($ka, $kb);
        if ($longueur >= 4 && $distance <= 1) {
            return 0.75;
        }
        if ($longueur >= 7 && $distance <= 2) {
            return 0.7;
        }

        return 0.0;
    }

    /** Mot reconnu malgré une petite faute de frappe (pour les mots-clés longs). */
    public static function correspond(string $mot, array $liste): bool
    {
        if (in_array($mot, $liste, true)) {
            return true;
        }
        if (strlen($mot) < 6) {
            return false;
        }
        foreach ($liste as $l) {
            if (strlen($l) >= 6 && levenshtein($mot, $l) <= 1) {
                return true;
            }
        }

        return false;
    }
}
