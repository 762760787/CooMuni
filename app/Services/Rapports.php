<?php

namespace App\Services;

use App\Enums\EtatCotisation;
use App\Enums\StatutCotisation;
use App\Models\Cotisation;
use App\Models\Membre;
use App\Models\OperationFinanciere;
use App\Models\Paiement;
use App\Support\Periode;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Rapports et états (§7.10, §19). Chaque rapport produit une structure unique
 * (résumé + colonnes + lignes + totaux) rendue à l'écran, en PDF et en Excel/CSV.
 *
 * Colonnes : ['cle', 'label', 'type' => texte|montant|date|etat|nombre, 'mobile' => titre|sous|droite|detail]
 */
class Rapports
{
    public const TYPES = [
        'etat_mensuel' => ['libelle' => 'État des cotisations mensuelles', 'params' => ['periode']],
        'membres_a_jour' => ['libelle' => 'Membres à jour', 'params' => ['periode']],
        'membres_en_retard' => ['libelle' => 'Membres ayant payé en retard', 'params' => ['periode']],
        'membres_impayes' => ['libelle' => 'Membres en impayé', 'params' => ['periode']],
        'historique_membre' => ['libelle' => 'Historique détaillé d\'un membre', 'params' => ['membre_id', 'annee']],
        'situation_financiere' => ['libelle' => 'Situation financière', 'params' => ['du', 'au']],
        'stats_mensuelles' => ['libelle' => 'Statistiques mensuelles', 'params' => ['annee']],
        'stats_annuelles' => ['libelle' => 'Statistiques annuelles', 'params' => []],
        'journal_paiements' => ['libelle' => 'Journal des paiements', 'params' => ['du', 'au']],
        'journal_operations' => ['libelle' => 'Journal des opérations financières', 'params' => ['du', 'au']],
    ];

    public function __construct(private Statistiques $stats, private Parametres $parametres) {}

    public function generer(string $type, array $p): array
    {
        if (! isset(self::TYPES[$type])) {
            throw ValidationException::withMessages(['type' => 'Type de rapport inconnu.']);
        }
        $this->valider($type, $p);

        $rapport = $this->{str($type)->camel()->toString()}($p);

        return $rapport + [
            'type' => $type,
            'genere_le' => now(),
            'cooperative' => $this->parametres->nomCooperative(),
            'totaux' => $rapport['totaux'] ?? null,
            'resume' => $rapport['resume'] ?? [],
        ];
    }

    private function valider(string $type, array $p): void
    {
        $erreurs = [];
        foreach (self::TYPES[$type]['params'] as $param) {
            $v = $p[$param] ?? null;
            $ok = match ($param) {
                'periode' => Periode::isValid($v),
                'annee' => is_numeric($v) && $v >= 2000 && $v <= 2100,
                'du', 'au' => is_string($v) && strtotime($v) !== false,
                'membre_id' => $v && Membre::whereKey($v)->exists(),
                default => true,
            };
            if (! $ok) {
                $erreurs[$param] = 'Valeur manquante ou invalide.';
            }
        }
        if (isset($p['du'], $p['au']) && ! $erreurs && $p['du'] > $p['au']) {
            $erreurs['au'] = 'La date de fin doit suivre la date de début.';
        }
        if ($erreurs) {
            throw ValidationException::withMessages($erreurs);
        }
    }

    private function colonnesCotisation(): array
    {
        return [
            ['cle' => 'matricule', 'label' => 'Matricule', 'type' => 'texte', 'mobile' => 'sous'],
            ['cle' => 'membre', 'label' => 'Membre', 'type' => 'texte', 'mobile' => 'titre'],
            ['cle' => 'service', 'label' => 'Service', 'type' => 'texte', 'mobile' => 'detail'],
            ['cle' => 'attendu', 'label' => 'Attendu', 'type' => 'montant', 'mobile' => 'detail'],
            ['cle' => 'paye', 'label' => 'Payé', 'type' => 'montant', 'mobile' => 'droite'],
            ['cle' => 'reste', 'label' => 'Reste', 'type' => 'montant', 'mobile' => 'detail'],
            ['cle' => 'date_paiement', 'label' => 'Date paiement', 'type' => 'date', 'mobile' => 'detail'],
            ['cle' => 'etat', 'label' => 'Statut', 'type' => 'etat', 'mobile' => 'detail'],
        ];
    }

    private function ligneCotisation(Cotisation $c): array
    {
        return [
            'matricule' => $c->membre->matricule,
            'membre' => $c->membre->nom_complet,
            'service' => $c->membre->service,
            'attendu' => $c->statut === StatutCotisation::Annule ? 0 : $c->montant_attendu,
            'paye' => $c->montant_paye,
            'reste' => $c->reste,
            'date_paiement' => $c->date_paiement?->toDateString(),
            'etat' => $c->etat(),
        ];
    }

    private function cotisationsPeriode(Periode $periode)
    {
        return Cotisation::with('membre')->where('periode', (string) $periode)
            ->join('membres', 'membres.id', '=', 'cotisations.membre_id')
            ->orderBy('membres.nom')->orderBy('membres.prenom')
            ->select('cotisations.*')->get();
    }

    private function etatMensuel(array $p): array
    {
        $periode = Periode::fromString($p['periode']);
        $s = $this->stats->periode($periode);
        $lignes = $this->cotisationsPeriode($periode)->map(fn ($c) => $this->ligneCotisation($c));

        return [
            'titre' => 'État des cotisations — '.$periode->libelle(),
            'sous_titre' => 'Échéance : '.$s['echeance']->format('d/m/Y'),
            'resume' => $this->resumePeriode($s),
            'colonnes' => $this->colonnesCotisation(),
            'lignes' => $lignes->all(),
            'totaux' => ['membre' => $lignes->count().' membre(s)', 'attendu' => $lignes->sum('attendu'), 'paye' => $lignes->sum('paye'), 'reste' => $lignes->sum('reste')],
        ];
    }

    private function resumePeriode(array $s): array
    {
        return [
            ['label' => 'Attendu', 'valeur' => fcfa($s['attendu'])],
            ['label' => 'Encaissé', 'valeur' => fcfa($s['encaisse'])],
            ['label' => 'Reste à recouvrer', 'valeur' => fcfa($s['reste'])],
            ['label' => 'Taux de recouvrement', 'valeur' => str_replace('.', ',', (string) $s['taux']).' %'],
            ['label' => 'À jour / en retard / impayés', 'valeur' => "{$s['a_jour']} / {$s['en_retard']} / {$s['impayes']}"],
        ];
    }

    private function membresAJour(array $p): array
    {
        $periode = Periode::fromString($p['periode']);
        $lignes = $this->cotisationsPeriode($periode)
            ->filter(fn ($c) => in_array($c->statut, [StatutCotisation::Paye, StatutCotisation::PayeRetard, StatutCotisation::Regularise], true))
            ->map(fn ($c) => $this->ligneCotisation($c))->values();

        return [
            'titre' => 'Membres à jour — '.$periode->libelle(),
            'sous_titre' => 'Cotisation soldée (à temps, en retard ou régularisée)',
            'resume' => [['label' => 'Membres à jour', 'valeur' => (string) $lignes->count()], ['label' => 'Montant encaissé', 'valeur' => fcfa($lignes->sum('paye'))]],
            'colonnes' => $this->colonnesCotisation(),
            'lignes' => $lignes->all(),
            'totaux' => ['membre' => $lignes->count().' membre(s)', 'attendu' => $lignes->sum('attendu'), 'paye' => $lignes->sum('paye'), 'reste' => $lignes->sum('reste')],
        ];
    }

    private function membresEnRetard(array $p): array
    {
        $periode = Periode::fromString($p['periode']);
        $lignes = $this->cotisationsPeriode($periode)
            ->filter(fn ($c) => $c->statut === StatutCotisation::PayeRetard)
            ->map(fn ($c) => $this->ligneCotisation($c) + ['jours' => $c->joursRetard()])->values();
        $colonnes = $this->colonnesCotisation();
        array_splice($colonnes, 7, 0, [['cle' => 'jours', 'label' => 'Jours de retard', 'type' => 'nombre', 'mobile' => 'detail']]);

        return [
            'titre' => 'Paiements en retard — '.$periode->libelle(),
            'sous_titre' => 'Cotisations réglées après l\'échéance du '.$periode->echeance($this->parametres->jourEcheance())->format('d/m/Y'),
            'resume' => [['label' => 'Membres en retard', 'valeur' => (string) $lignes->count()], ['label' => 'Retard moyen', 'valeur' => round((float) $lignes->avg('jours'), 1).' j']],
            'colonnes' => $colonnes,
            'lignes' => $lignes->all(),
            'totaux' => ['membre' => $lignes->count().' membre(s)', 'paye' => $lignes->sum('paye')],
        ];
    }

    private function membresImpayes(array $p): array
    {
        $periode = Periode::fromString($p['periode']);
        $rows = Cotisation::impayees()->where('periode', '<=', (string) $periode)
            ->selectRaw('membre_id, COUNT(*) as nb_mois, SUM(montant_attendu - montant_paye) as montant_du, MIN(periode) as premiere, MAX(periode) as derniere')
            ->groupBy('membre_id')->get();
        $membres = Membre::whereIn('id', $rows->pluck('membre_id'))->get()->keyBy('id');
        $lignes = $rows->map(fn ($r) => [
            'matricule' => $membres[$r->membre_id]->matricule,
            'membre' => $membres[$r->membre_id]->nom_complet,
            'service' => $membres[$r->membre_id]->service,
            'telephone' => $membres[$r->membre_id]->telephone,
            'nb_mois' => (int) $r->nb_mois,
            'periodes' => $r->premiere === $r->derniere ? periode_libelle($r->premiere) : periode_libelle($r->premiere).' → '.periode_libelle($r->derniere),
            'montant_du' => (int) $r->montant_du,
        ])->sortBy('membre')->values();

        return [
            'titre' => 'Membres en impayé — jusqu\'à '.$periode->libelle(),
            'sous_titre' => 'Cotisations non soldées dont l\'échéance est dépassée au '.today()->format('d/m/Y'),
            'resume' => [
                ['label' => 'Membres concernés', 'valeur' => (string) $lignes->count()],
                ['label' => 'Mois impayés', 'valeur' => (string) $lignes->sum('nb_mois')],
                ['label' => 'Montant dû', 'valeur' => fcfa($lignes->sum('montant_du'))],
            ],
            'colonnes' => [
                ['cle' => 'matricule', 'label' => 'Matricule', 'type' => 'texte', 'mobile' => 'sous'],
                ['cle' => 'membre', 'label' => 'Membre', 'type' => 'texte', 'mobile' => 'titre'],
                ['cle' => 'service', 'label' => 'Service', 'type' => 'texte', 'mobile' => 'detail'],
                ['cle' => 'telephone', 'label' => 'Téléphone', 'type' => 'texte', 'mobile' => 'detail'],
                ['cle' => 'nb_mois', 'label' => 'Mois impayés', 'type' => 'nombre', 'mobile' => 'detail'],
                ['cle' => 'periodes', 'label' => 'Périodes', 'type' => 'texte', 'mobile' => 'detail'],
                ['cle' => 'montant_du', 'label' => 'Montant dû', 'type' => 'montant', 'mobile' => 'droite'],
            ],
            'lignes' => $lignes->all(),
            'totaux' => ['membre' => $lignes->count().' membre(s)', 'nb_mois' => $lignes->sum('nb_mois'), 'montant_du' => $lignes->sum('montant_du')],
        ];
    }

    private function historiqueMembre(array $p): array
    {
        $membre = Membre::findOrFail($p['membre_id']);
        $annee = (int) $p['annee'];
        $cotisations = Cotisation::where('membre_id', $membre->id)->where('annee', $annee)
            ->with(['paiements' => fn ($q) => $q->where('statut', 'valide')])->orderBy('periode')->get();
        $s = $this->stats->membre($membre);

        return [
            'titre' => 'Historique de '.$membre->nom_complet.' — '.$annee,
            'sous_titre' => 'Matricule '.$membre->matricule.($membre->service ? ' · '.$membre->service : '').' · Statut : '.$membre->statut->label(),
            'resume' => [
                ['label' => 'Mois payés', 'valeur' => (string) $s['mois_payes']],
                ['label' => 'Retards', 'valeur' => (string) $s['retards']],
                ['label' => 'Impayés', 'valeur' => (string) $s['impayes']],
                ['label' => 'Total versé', 'valeur' => fcfa($s['total_verse'])],
                ['label' => 'Solde dû (échu)', 'valeur' => fcfa($s['solde_du'])],
            ],
            'colonnes' => [
                ['cle' => 'periode', 'label' => 'Mois', 'type' => 'texte', 'mobile' => 'titre'],
                ['cle' => 'attendu', 'label' => 'Attendu', 'type' => 'montant', 'mobile' => 'detail'],
                ['cle' => 'paye', 'label' => 'Payé', 'type' => 'montant', 'mobile' => 'droite'],
                ['cle' => 'date_paiement', 'label' => 'Date paiement', 'type' => 'date', 'mobile' => 'detail'],
                ['cle' => 'recus', 'label' => 'Reçu(s)', 'type' => 'texte', 'mobile' => 'detail'],
                ['cle' => 'etat', 'label' => 'Statut', 'type' => 'etat', 'mobile' => 'detail'],
            ],
            'lignes' => $cotisations->map(fn ($c) => [
                'periode' => $c->periode_libelle,
                'attendu' => $c->statut === StatutCotisation::Annule ? 0 : $c->montant_attendu,
                'paye' => $c->montant_paye,
                'date_paiement' => $c->date_paiement?->toDateString(),
                'recus' => $c->paiements->pluck('numero_recu')->implode(', '),
                'etat' => $c->etat(),
            ])->all(),
            'totaux' => ['periode' => 'Total '.$annee, 'attendu' => $cotisations->where('statut', '!=', StatutCotisation::Annule)->sum('montant_attendu'), 'paye' => $cotisations->sum('montant_paye')],
        ];
    }

    private function situationFinanciere(array $p): array
    {
        $s = $this->stats->situationFinanciere($p['du'], $p['au']);
        $lignes = [['rubrique' => 'Solde d\'ouverture au '.date_fr($p['du']), 'type' => '', 'nombre' => null, 'montant' => $s['solde_ouverture']]];
        $lignes[] = ['rubrique' => 'Cotisations encaissées', 'type' => 'Entrée', 'nombre' => null, 'montant' => $s['cotisations']];
        foreach ($s['entrees_par_categorie'] as $e) {
            $lignes[] = ['rubrique' => $e->categorie, 'type' => 'Entrée', 'nombre' => (int) $e->nombre, 'montant' => (int) $e->total];
        }
        foreach ($s['sorties_par_categorie'] as $e) {
            $lignes[] = ['rubrique' => $e->categorie, 'type' => 'Sortie', 'nombre' => (int) $e->nombre, 'montant' => -(int) $e->total];
        }
        $lignes[] = ['rubrique' => 'Solde de clôture au '.date_fr($p['au']), 'type' => '', 'nombre' => null, 'montant' => $s['solde_cloture']];

        return [
            'titre' => 'Situation financière',
            'sous_titre' => 'Du '.date_fr($p['du']).' au '.date_fr($p['au']),
            'resume' => [
                ['label' => 'Solde d\'ouverture', 'valeur' => fcfa($s['solde_ouverture'])],
                ['label' => 'Total des entrées', 'valeur' => fcfa($s['total_entrees'])],
                ['label' => 'Total des sorties', 'valeur' => fcfa($s['total_sorties'])],
                ['label' => 'Solde de clôture', 'valeur' => fcfa($s['solde_cloture'])],
            ],
            'colonnes' => [
                ['cle' => 'rubrique', 'label' => 'Rubrique', 'type' => 'texte', 'mobile' => 'titre'],
                ['cle' => 'type', 'label' => 'Type', 'type' => 'texte', 'mobile' => 'sous'],
                ['cle' => 'nombre', 'label' => 'Nb opérations', 'type' => 'nombre', 'mobile' => 'detail'],
                ['cle' => 'montant', 'label' => 'Montant', 'type' => 'montant', 'mobile' => 'droite'],
            ],
            'lignes' => $lignes,
        ];
    }

    private function statsMensuelles(array $p): array
    {
        $annee = (int) $p['annee'];
        $lignes = [];
        foreach (range(1, 12) as $m) {
            $periode = new Periode($annee, $m);
            $s = $this->stats->periode($periode);
            $ops = OperationFinanciere::valides()->whereYear('date_operation', $annee)->whereMonth('date_operation', $m)
                ->selectRaw("SUM(CASE WHEN type='entree' THEN montant ELSE 0 END) e, SUM(CASE WHEN type='sortie' THEN montant ELSE 0 END) s")->first();
            if ($s['total'] === 0 && ! $ops->e && ! $ops->s) {
                continue;
            }
            $lignes[] = [
                'mois' => $periode->libelle(), 'attendu' => $s['attendu'], 'encaisse' => $s['encaisse'], 'taux' => $s['taux'].' %',
                'a_temps' => $s['a_temps'], 'retard' => $s['en_retard'], 'impayes' => $s['impayes'],
                'entrees' => (int) $ops->e, 'sorties' => (int) $ops->s,
            ];
        }
        $c = collect($lignes);

        return [
            'titre' => 'Statistiques mensuelles '.$annee,
            'sous_titre' => 'Cotisations par période et mouvements de caisse par mois',
            'resume' => [
                ['label' => 'Total attendu', 'valeur' => fcfa($c->sum('attendu'))],
                ['label' => 'Total encaissé', 'valeur' => fcfa($c->sum('encaisse'))],
                ['label' => 'Autres entrées', 'valeur' => fcfa($c->sum('entrees'))],
                ['label' => 'Sorties', 'valeur' => fcfa($c->sum('sorties'))],
            ],
            'colonnes' => [
                ['cle' => 'mois', 'label' => 'Mois', 'type' => 'texte', 'mobile' => 'titre'],
                ['cle' => 'attendu', 'label' => 'Attendu', 'type' => 'montant', 'mobile' => 'detail'],
                ['cle' => 'encaisse', 'label' => 'Encaissé', 'type' => 'montant', 'mobile' => 'droite'],
                ['cle' => 'taux', 'label' => 'Taux', 'type' => 'texte', 'mobile' => 'sous'],
                ['cle' => 'a_temps', 'label' => 'À temps', 'type' => 'nombre', 'mobile' => 'detail'],
                ['cle' => 'retard', 'label' => 'En retard', 'type' => 'nombre', 'mobile' => 'detail'],
                ['cle' => 'impayes', 'label' => 'Impayés', 'type' => 'nombre', 'mobile' => 'detail'],
                ['cle' => 'entrees', 'label' => 'Autres entrées', 'type' => 'montant', 'mobile' => 'detail'],
                ['cle' => 'sorties', 'label' => 'Sorties', 'type' => 'montant', 'mobile' => 'detail'],
            ],
            'lignes' => $lignes,
            'totaux' => ['mois' => 'Total', 'attendu' => $c->sum('attendu'), 'encaisse' => $c->sum('encaisse'), 'a_temps' => $c->sum('a_temps'),
                'retard' => $c->sum('retard'), 'impayes' => $c->sum('impayes'), 'entrees' => $c->sum('entrees'), 'sorties' => $c->sum('sorties')],
        ];
    }

    private function statsAnnuelles(array $p): array
    {
        $annees = Cotisation::query()->distinct()->orderBy('annee')->pluck('annee');
        $lignes = $annees->map(function ($annee) {
            $c = Cotisation::where('annee', $annee)->selectRaw("
                SUM(CASE WHEN statut <> 'annule' THEN montant_attendu ELSE 0 END) attendu,
                SUM(CASE WHEN statut <> 'annule' THEN montant_paye ELSE 0 END) encaisse,
                SUM(CASE WHEN statut = 'paye_retard' THEN 1 ELSE 0 END) retards,
                COUNT(DISTINCT membre_id) membres")->first();
            $ops = OperationFinanciere::valides()->whereYear('date_operation', $annee)
                ->selectRaw("SUM(CASE WHEN type='entree' THEN montant ELSE 0 END) e, SUM(CASE WHEN type='sortie' THEN montant ELSE 0 END) s")->first();

            return [
                'annee' => (string) $annee, 'membres' => (int) $c->membres, 'attendu' => (int) $c->attendu, 'encaisse' => (int) $c->encaisse,
                'taux' => ($c->attendu > 0 ? round($c->encaisse * 100 / $c->attendu, 1) : 0).' %', 'retards' => (int) $c->retards,
                'entrees' => (int) $ops->e, 'sorties' => (int) $ops->s,
            ];
        });

        return [
            'titre' => 'Statistiques annuelles',
            'sous_titre' => 'Synthèse par exercice',
            'resume' => [['label' => 'Exercices', 'valeur' => (string) $lignes->count()], ['label' => 'Solde de caisse actuel', 'valeur' => fcfa($this->stats->soldeCaisse())]],
            'colonnes' => [
                ['cle' => 'annee', 'label' => 'Année', 'type' => 'texte', 'mobile' => 'titre'],
                ['cle' => 'membres', 'label' => 'Membres cotisants', 'type' => 'nombre', 'mobile' => 'detail'],
                ['cle' => 'attendu', 'label' => 'Attendu', 'type' => 'montant', 'mobile' => 'detail'],
                ['cle' => 'encaisse', 'label' => 'Encaissé', 'type' => 'montant', 'mobile' => 'droite'],
                ['cle' => 'taux', 'label' => 'Taux', 'type' => 'texte', 'mobile' => 'sous'],
                ['cle' => 'retards', 'label' => 'Paiements en retard', 'type' => 'nombre', 'mobile' => 'detail'],
                ['cle' => 'entrees', 'label' => 'Autres entrées', 'type' => 'montant', 'mobile' => 'detail'],
                ['cle' => 'sorties', 'label' => 'Sorties', 'type' => 'montant', 'mobile' => 'detail'],
            ],
            'lignes' => $lignes->all(),
        ];
    }

    private function journalPaiements(array $p): array
    {
        $paiements = Paiement::with(['membre', 'modePaiement', 'enregistrePar', 'cotisations'])
            ->whereBetween('date_paiement', [$p['du'], $p['au']])->orderBy('date_paiement')->orderBy('id')->get();
        $valides = $paiements->where('statut', 'valide');

        return [
            'titre' => 'Journal des paiements',
            'sous_titre' => 'Du '.date_fr($p['du']).' au '.date_fr($p['au']),
            'resume' => [
                ['label' => 'Paiements valides', 'valeur' => (string) $valides->count()],
                ['label' => 'Montant encaissé', 'valeur' => fcfa($valides->sum('montant'))],
                ['label' => 'Paiements annulés', 'valeur' => (string) ($paiements->count() - $valides->count())],
            ],
            'colonnes' => [
                ['cle' => 'numero', 'label' => 'Reçu', 'type' => 'texte', 'mobile' => 'sous'],
                ['cle' => 'date', 'label' => 'Date', 'type' => 'date', 'mobile' => 'detail'],
                ['cle' => 'membre', 'label' => 'Membre', 'type' => 'texte', 'mobile' => 'titre'],
                ['cle' => 'periodes', 'label' => 'Périodes', 'type' => 'texte', 'mobile' => 'detail'],
                ['cle' => 'mode', 'label' => 'Mode', 'type' => 'texte', 'mobile' => 'detail'],
                ['cle' => 'montant', 'label' => 'Montant', 'type' => 'montant', 'mobile' => 'droite'],
                ['cle' => 'statut', 'label' => 'Statut', 'type' => 'texte', 'mobile' => 'detail'],
                ['cle' => 'par', 'label' => 'Saisi par', 'type' => 'texte', 'mobile' => 'detail'],
            ],
            'lignes' => $paiements->map(fn ($x) => [
                'numero' => $x->numero_recu, 'date' => $x->date_paiement->toDateString(), 'membre' => $x->membre->nom_complet,
                'periodes' => $x->libellePeriodes(), 'mode' => $x->modePaiement->nom, 'montant' => $x->montant,
                'statut' => $x->estAnnule() ? 'Annulé' : 'Validé', 'par' => $x->enregistrePar->name,
            ])->all(),
            'totaux' => ['membre' => 'Total validé', 'montant' => $valides->sum('montant')],
        ];
    }

    private function journalOperations(array $p): array
    {
        $ops = OperationFinanciere::with(['categorie', 'enregistrePar'])
            ->whereBetween('date_operation', [$p['du'], $p['au']])->orderBy('date_operation')->orderBy('id')->get();
        $valides = $ops->where('statut', 'valide');

        return [
            'titre' => 'Journal des opérations financières',
            'sous_titre' => 'Du '.date_fr($p['du']).' au '.date_fr($p['au']),
            'resume' => [
                ['label' => 'Entrées', 'valeur' => fcfa($valides->where('type', 'entree')->sum('montant'))],
                ['label' => 'Sorties', 'valeur' => fcfa($valides->where('type', 'sortie')->sum('montant'))],
                ['label' => 'Opérations annulées', 'valeur' => (string) ($ops->count() - $valides->count())],
            ],
            'colonnes' => [
                ['cle' => 'numero', 'label' => 'N°', 'type' => 'texte', 'mobile' => 'sous'],
                ['cle' => 'date', 'label' => 'Date', 'type' => 'date', 'mobile' => 'detail'],
                ['cle' => 'categorie', 'label' => 'Catégorie', 'type' => 'texte', 'mobile' => 'titre'],
                ['cle' => 'description', 'label' => 'Description', 'type' => 'texte', 'mobile' => 'detail'],
                ['cle' => 'type', 'label' => 'Type', 'type' => 'texte', 'mobile' => 'detail'],
                ['cle' => 'montant', 'label' => 'Montant', 'type' => 'montant', 'mobile' => 'droite'],
                ['cle' => 'statut', 'label' => 'Statut', 'type' => 'texte', 'mobile' => 'detail'],
                ['cle' => 'par', 'label' => 'Saisi par', 'type' => 'texte', 'mobile' => 'detail'],
            ],
            'lignes' => $ops->map(fn ($o) => [
                'numero' => $o->numero, 'date' => $o->date_operation->toDateString(), 'categorie' => $o->categorie->nom,
                'description' => $o->description, 'type' => $o->estEntree() ? 'Entrée' : 'Sortie',
                'montant' => $o->estEntree() ? $o->montant : -$o->montant,
                'statut' => $o->estAnnule() ? 'Annulée' : 'Validée', 'par' => $o->enregistrePar->name,
            ])->all(),
            'totaux' => ['categorie' => 'Solde des opérations validées', 'montant' => $valides->where('type', 'entree')->sum('montant') - $valides->where('type', 'sortie')->sum('montant')],
        ];
    }

    /** Valeur textuelle d'une cellule (exports). */
    public static function texte(mixed $v, string $type): string
    {
        return match (true) {
            $v instanceof EtatCotisation => $v->label(),
            $v === null || $v === '' => '',
            $type === 'montant' => fcfa($v, false),
            $type === 'date' => date_fr($v),
            default => (string) $v,
        };
    }

    /** Paramètres par défaut raisonnables pour l'écran. */
    public static function parametresParDefaut(): array
    {
        $today = CarbonImmutable::today();
        // Début de l'exercice en cours, selon le mois paramétré (§7.12 « exercice »).
        $moisExercice = max(1, min(12, (int) parametre('exercice_debut_mois', 1)));
        $debutExercice = $today->setDate((int) $today->format('Y'), $moisExercice, 1);
        if ($debutExercice->gt($today)) {
            $debutExercice = $debutExercice->subYear();
        }

        return [
            'periode' => (string) Periode::courante(),
            'annee' => (int) $today->format('Y'),
            'du' => $debutExercice->toDateString(),
            'au' => $today->toDateString(),
            'membre_id' => null,
        ];
    }
}
