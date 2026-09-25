<?php

namespace App\Services;

use App\Models\Parametre;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Accès aux paramètres métier stockés en base (§7.12, §8.1).
 * Aucune valeur métier n'est codée en dur : un paramètre obligatoire absent
 * lève une exception explicite plutôt que de retomber sur une valeur implicite.
 */
class Parametres
{
    private const CACHE_KEY = 'coop.parametres';

    private ?array $valeurs = null;

    public function all(): array
    {
        if ($this->valeurs !== null) {
            return $this->valeurs;
        }

        return $this->valeurs = Cache::rememberForever(self::CACHE_KEY, function () {
            if (! Schema::hasTable('parametres')) {
                return [];
            }

            return Parametre::all()->mapWithKeys(fn (Parametre $p) => [$p->cle => $p->valeurTypee()])->all();
        });
    }

    public function get(string $cle, mixed $defaut = null): mixed
    {
        return $this->all()[$cle] ?? $defaut;
    }

    public function requis(string $cle): mixed
    {
        $valeur = $this->all()[$cle] ?? null;
        if ($valeur === null || $valeur === '') {
            throw new RuntimeException("Paramètre obligatoire non défini : « {$cle} ». Renseignez-le dans Paramètres.");
        }

        return $valeur;
    }

    public function int(string $cle): int
    {
        return (int) $this->requis($cle);
    }

    public function bool(string $cle): bool
    {
        return (bool) ($this->all()[$cle] ?? false);
    }

    /** Met à jour un paramètre avec trace d'audit. */
    public function set(string $cle, mixed $valeur): void
    {
        $param = Parametre::where('cle', $cle)->firstOrFail();
        $ancienne = $param->valeur;
        $nouvelle = is_bool($valeur) ? ($valeur ? '1' : '0') : ($valeur === null ? null : (string) $valeur);

        if ($ancienne === $nouvelle) {
            return;
        }

        $param->update(['valeur' => $nouvelle]);
        Audit::log('parametre.modifier', $param, ['valeur' => $ancienne], ['valeur' => $nouvelle], $param->libelle);
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->valeurs = null;
    }

    // Raccourcis métier -----------------------------------------------------

    public function montantCotisation(): int
    {
        return $this->int('cotisation_montant');
    }

    public function jourEcheance(): int
    {
        return $this->int('cotisation_jour_echeance');
    }

    public function nomCooperative(): string
    {
        return (string) $this->get('coop_nom', config('app.name'));
    }
}
