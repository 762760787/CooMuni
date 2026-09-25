<?php

namespace App\Services\Assistant\Local;

use App\Models\Membre;
use Illuminate\Support\Collection;

/**
 * Retrouve le membre visé par une phrase, en tolérant les fautes de frappe et
 * les orthographes wolof / française, et en signalant les homonymes.
 */
final class ResolveurMembre
{
    public const UNIQUE = 'unique';
    public const AMBIGU = 'ambigu';
    public const INTROUVABLE = 'introuvable';
    public const AUCUN_NOM = 'aucun_nom';

    private const SEUIL = 0.7;

    /** @var Collection<int, array{membre: Membre, nom: list<string>, prenom: list<string>}>|null */
    private ?Collection $index = null;

    public function __construct(private string $prefixeMatricule = 'NGD-') {}

    private function index(): Collection
    {
        return $this->index ??= Membre::query()->orderBy('nom')->orderBy('prenom')->get()->map(fn (Membre $m) => [
            'membre' => $m,
            'nom' => Texte::mots(Texte::normaliser($m->nom)),
            // Les particules très courtes (« El ») ne servent pas à identifier.
            'prenom' => array_values(array_filter(Texte::mots(Texte::normaliser($m->prenom)), fn ($p) => strlen($p) > 2)),
        ]);
    }

    /**
     * @param  list<int>|null  $parmi  restreindre la recherche à ces identifiants (réponse à « lequel ? »)
     * @return array{statut: string, membres: list<Membre>}
     */
    public function resoudre(Analyse $a, ?array $parmi = null): array
    {
        $index = $parmi ? $this->index()->filter(fn ($e) => in_array($e['membre']->id, $parmi, true)) : $this->index();

        if ($a->matricule !== null) {
            $trouve = $index->first(fn ($e) => (int) preg_replace('/\D/', '', $e['membre']->matricule) === (int) $a->matricule);
            if ($trouve) {
                return ['statut' => self::UNIQUE, 'membres' => [$trouve['membre']]];
            }
        }
        if ($a->telephone !== null) {
            $trouve = $index->first(fn ($e) => $e['membre']->telephone && str_ends_with(preg_replace('/\D/', '', $e['membre']->telephone), $a->telephone));
            if ($trouve) {
                return ['statut' => self::UNIQUE, 'membres' => [$trouve['membre']]];
            }
        }
        if (! $a->motsNom) {
            return ['statut' => self::AUCUN_NOM, 'membres' => []];
        }

        $scores = [];
        foreach ($index as $e) {
            [$niveau, $qualite] = $this->score($e, $a->motsNom);
            if ($niveau > 0) {
                $scores[] = ['membre' => $e['membre'], 'score' => $niveau * 10 + $qualite, 'niveau' => $niveau];
            }
        }
        if (! $scores) {
            return ['statut' => self::INTROUVABLE, 'membres' => []];
        }
        usort($scores, fn ($x, $y) => $y['score'] <=> $x['score']);
        $meilleur = $scores[0];
        $proches = array_values(array_filter($scores, fn ($s) => $meilleur['score'] - $s['score'] <= 0.6));

        return count($proches) === 1
            ? ['statut' => self::UNIQUE, 'membres' => [$meilleur['membre']]]
            : ['statut' => self::AMBIGU, 'membres' => array_slice(array_column($proches, 'membre'), 0, 8)];
    }

    /**
     * Associe chaque partie du nom à un mot DIFFÉRENT de la phrase (nom de famille d'abord),
     * puis classe : niveau 3 = nom + prénom, 2 = nom seul, 1 = prénom seul.
     *
     * @return array{0: int, 1: float} [niveau, somme des ressemblances]
     */
    private function score(array $e, array $mots): array
    {
        $disponibles = $mots;
        $prendre = function (string $partie) use (&$disponibles): float {
            $best = 0.0;
            $cle = null;
            foreach ($disponibles as $i => $m) {
                $q = Texte::ressemblance($m, $partie);
                if ($q > $best) {
                    [$best, $cle] = [$q, $i];
                }
            }
            if ($best >= self::SEUIL) {
                unset($disponibles[$cle]);

                return $best;
            }

            return 0.0;
        };

        $qualiteNom = 0.0;
        $nomOk = (bool) $e['nom'];
        foreach ($e['nom'] as $partie) {
            $q = $prendre($partie);
            $nomOk = $nomOk && $q > 0;
            $qualiteNom += $q;
        }
        if (! $nomOk) {
            // Nom non reconnu : les mots éventuellement pris sont rendus pour le prénom.
            $disponibles = $mots;
            $qualiteNom = 0.0;
        }
        $qualitePrenom = 0.0;
        $prenomsTrouves = 0;
        foreach ($e['prenom'] as $partie) {
            $q = $prendre($partie);
            if ($q > 0) {
                $prenomsTrouves++;
                $qualitePrenom += $q;
            }
        }

        $niveau = match (true) {
            $nomOk && $prenomsTrouves > 0 => 3,
            $nomOk => 2,
            $prenomsTrouves > 0 => 1,
            default => 0,
        };

        return [$niveau, $qualiteNom + $qualitePrenom];
    }
}
