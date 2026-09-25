<?php

namespace App\Services;

use App\Enums\StatutMembre;
use App\Models\Membre;
use App\Models\MembreStatut;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Import du fichier du personnel (.docx fourni par la commune, ou .csv).
 * Colonnes attendues : N° | Prénom | Nom | Téléphone | (Montant ignoré).
 * Le N° de la liste devient le matricule : préfixe paramétré + N° sur 3 chiffres.
 */
class ImportMembres
{
    public function __construct(private Parametres $parametres) {}

    /** @return list<array{numero:int, prenom:string, nom:string, telephone:?string}> */
    public function lire(string $chemin): array
    {
        if (! is_file($chemin)) {
            throw new RuntimeException("Fichier introuvable : {$chemin}");
        }
        $lignes = match (strtolower(pathinfo($chemin, PATHINFO_EXTENSION))) {
            'docx' => $this->lireDocx($chemin),
            'csv', 'txt' => $this->lireCsv($chemin),
            default => throw new RuntimeException('Format non pris en charge (attendu : .docx ou .csv).'),
        };

        $corrections = is_file($f = database_path('seeders/data/corrections_personnel.php')) ? require $f : [];
        $out = [];
        foreach ($lignes as $cells) {
            $cells = array_map(fn ($c) => trim(preg_replace('/\s+/u', ' ', (string) $c)), $cells);
            if (count($cells) < 3 || ! ctype_digit($cells[0])) {
                continue; // en-tête ou ligne vide
            }
            $out[] = array_merge([
                'numero' => (int) $cells[0],
                'prenom' => $cells[1],
                'nom' => $cells[2],
                'telephone' => MembreService::normaliserTelephone($cells[3] ?? null),
            ], $corrections[(int) $cells[0]] ?? []);
        }

        return $out;
    }

    /**
     * @return array{crees:int, existants:int, avertissements:list<string>}
     */
    public function importer(array $lignes, string $dateAdhesion, bool $simulation = false): array
    {
        $prefixe = (string) $this->parametres->requis('matricule_prefixe');
        $rapport = ['crees' => 0, 'existants' => 0, 'avertissements' => []];

        // Signalements de qualité des données (ne bloquent pas l'import).
        $parNom = collect($lignes)->groupBy(fn ($l) => mb_strtoupper($l['prenom'].' '.$l['nom']));
        foreach ($parNom as $nom => $groupe) {
            if ($groupe->count() > 1) {
                $rapport['avertissements'][] = "Homonymes « {$nom} » : N° ".$groupe->pluck('numero')->implode(', ').' (importés comme membres distincts).';
            }
        }
        foreach ($lignes as $l) {
            if (mb_strtoupper($l['prenom']) === mb_strtoupper($l['nom'])) {
                $rapport['avertissements'][] = "N° {$l['numero']} : le nom « {$l['nom']} » est identique au prénom — nom de famille probablement manquant, à vérifier.";
            }
        }
        $numeros = collect($lignes)->pluck('numero')->sort()->values();
        if ($numeros->isNotEmpty()) {
            $manquants = array_diff(range($numeros->first(), $numeros->last()), $numeros->all());
            if ($manquants) {
                $rapport['avertissements'][] = 'Numéros absents de la liste : '.implode(', ', $manquants).'.';
            }
        }

        $run = function () use ($lignes, $prefixe, $dateAdhesion, $simulation, &$rapport) {
            foreach ($lignes as $l) {
                $matricule = $prefixe.str_pad((string) $l['numero'], 3, '0', STR_PAD_LEFT);
                if (Membre::where('matricule', $matricule)->exists()) {
                    $rapport['existants']++;

                    continue;
                }
                if ($l['telephone'] && Membre::where('telephone', $l['telephone'])->exists()) {
                    $rapport['avertissements'][] = "N° {$l['numero']} : téléphone {$l['telephone']} déjà utilisé — importé sans téléphone.";
                    $l['telephone'] = null;
                }
                if ($simulation) {
                    $rapport['crees']++;

                    continue;
                }
                $membre = Membre::create([
                    'matricule' => $matricule,
                    'nom' => mb_strtoupper($l['nom']),
                    'prenom' => Str::title(mb_strtolower($l['prenom'])),
                    'telephone' => $l['telephone'],
                    'date_adhesion' => $dateAdhesion,
                    'statut' => StatutMembre::Actif,
                    'observations' => mb_strtoupper($l['prenom']) === mb_strtoupper($l['nom'])
                        ? 'Import : nom de famille à vérifier (identique au prénom dans la liste d\'origine).' : null,
                ]);
                MembreStatut::create([
                    'membre_id' => $membre->id, 'nouveau_statut' => StatutMembre::Actif,
                    'date_effet' => $dateAdhesion, 'motif' => 'Adhésion (import liste du personnel)',
                ]);
                $rapport['crees']++;
            }
        };
        $simulation ? $run() : DB::transaction($run);

        if (! $simulation && $rapport['crees'] > 0) {
            Audit::log('membre.import', 'Membre', null, [
                'crees' => $rapport['crees'], 'existants' => $rapport['existants'], 'date_adhesion' => $dateAdhesion,
            ], "Import de {$rapport['crees']} membre(s) depuis la liste du personnel");
        }

        return $rapport;
    }

    /** Lecture des tableaux d'un .docx (WordprocessingML) sans dépendance externe. */
    private function lireDocx(string $chemin): array
    {
        $zip = new ZipArchive;
        if ($zip->open($chemin) !== true) {
            throw new RuntimeException('Impossible d\'ouvrir le fichier Word.');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false) {
            throw new RuntimeException('Document Word invalide.');
        }

        $dom = new \DOMDocument;
        $dom->loadXML($xml, LIBXML_NONET); // pas de substitution d'entités ni d'accès réseau (XXE)
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $lignes = [];
        foreach ($xp->query('//w:tbl//w:tr') as $tr) {
            $cells = [];
            foreach ($xp->query('./w:tc', $tr) as $tc) {
                $texte = [];
                foreach ($xp->query('.//w:p', $tc) as $p) {
                    $texte[] = implode('', array_map(fn ($t) => $t->textContent, iterator_to_array($xp->query('.//w:t', $p))));
                }
                $cells[] = implode(' ', $texte);
            }
            $lignes[] = $cells;
        }

        return $lignes;
    }

    private function lireCsv(string $chemin): array
    {
        $contenu = file_get_contents($chemin);
        $contenu = preg_replace('/^\xEF\xBB\xBF/', '', $contenu); // BOM
        $sep = substr_count(strtok($contenu, "\n"), ';') >= substr_count(strtok($contenu, "\n"), ',') ? ';' : ',';

        return array_map(fn ($l) => str_getcsv($l, $sep, '"', ''), preg_split('/\r\n|\n|\r/', trim($contenu)));
    }
}
