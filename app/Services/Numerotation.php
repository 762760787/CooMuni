<?php

namespace App\Services;

use App\Models\Sequence;
use Illuminate\Support\Facades\DB;

/**
 * Numérotation séquentielle sans trou ni doublon (reçus, opérations).
 * Doit être appelée à l'intérieur d'une transaction : la ligne de compteur
 * est verrouillée (SELECT ... FOR UPDATE) jusqu'au commit.
 */
class Numerotation
{
    public function __construct(private Parametres $parametres) {}

    public function suivant(string $nom, string $clePrefixe, ?int $annee = null): string
    {
        $annee ??= (int) now()->format('Y');

        return DB::transaction(function () use ($nom, $clePrefixe, $annee) {
            $seq = Sequence::where('nom', $nom)->where('annee', $annee)->lockForUpdate()->first();
            if (! $seq) {
                Sequence::insertOrIgnore(['nom' => $nom, 'annee' => $annee, 'valeur' => 0, 'created_at' => now(), 'updated_at' => now()]);
                $seq = Sequence::where('nom', $nom)->where('annee', $annee)->lockForUpdate()->first();
            }
            $seq->increment('valeur');

            $prefixe = (string) $this->parametres->requis($clePrefixe);

            return sprintf('%s-%d-%05d', $prefixe, $annee, $seq->valeur);
        });
    }
}
