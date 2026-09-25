<?php

namespace App\Services;

use App\Enums\StatutMembre;
use App\Models\Cotisation;
use App\Models\Membre;
use App\Models\OperationFinanciere;
use App\Models\Paiement;
use App\Support\Periode;

/**
 * Indicateurs et agrégats (§7.2, §7.3, §7.4). Requêtes agrégées côté SQL
 * pour rester performantes avec plusieurs milliers de membres (§21).
 */
class Statistiques
{
    public function __construct(private Parametres $parametres) {}

    /**
     * Indicateurs d'une période : attendu, encaissé, reste, taux, répartition des membres.
     */
    public function periode(Periode $periode): array
    {
        $today = today()->toDateString();
        $r = Cotisation::where('periode', (string) $periode)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN statut <> 'annule' THEN montant_attendu ELSE 0 END) as attendu,
                SUM(CASE WHEN statut <> 'annule' THEN montant_paye ELSE 0 END) as encaisse,
                SUM(CASE WHEN statut IN ('a_payer','partiel') THEN montant_attendu - montant_paye ELSE 0 END) as reste_du,
                SUM(CASE WHEN statut = 'paye' THEN 1 ELSE 0 END) as a_temps,
                SUM(CASE WHEN statut = 'paye_retard' THEN 1 ELSE 0 END) as en_retard,
                SUM(CASE WHEN statut = 'regularise' THEN 1 ELSE 0 END) as regularises,
                SUM(CASE WHEN statut = 'partiel' THEN 1 ELSE 0 END) as partiels,
                SUM(CASE WHEN statut IN ('a_payer','partiel') AND date_echeance < ? THEN 1 ELSE 0 END) as impayes,
                SUM(CASE WHEN statut IN ('a_payer','partiel') AND date_echeance >= ? THEN 1 ELSE 0 END) as a_venir,
                SUM(CASE WHEN statut = 'annule' THEN 1 ELSE 0 END) as annules
            ", [$today, $today])
            ->first();

        $attendu = (int) $r->attendu;
        $encaisse = (int) $r->encaisse;

        return [
            'periode' => $periode,
            'echeance' => $periode->echeance($this->parametres->jourEcheance()),
            'total' => (int) $r->total,
            'attendu' => $attendu,
            'encaisse' => $encaisse,
            'reste' => (int) $r->reste_du,
            'taux' => $attendu > 0 ? round($encaisse * 100 / $attendu, 1) : 0.0,
            'a_temps' => (int) $r->a_temps,
            'en_retard' => (int) $r->en_retard,
            'a_jour' => (int) $r->a_temps + (int) $r->en_retard + (int) $r->regularises,
            'regularises' => (int) $r->regularises,
            'partiels' => (int) $r->partiels,
            'impayes' => (int) $r->impayes,
            'a_venir' => (int) $r->a_venir,
            'annules' => (int) $r->annules,
        ];
    }

    public function effectifs(): array
    {
        $parStatut = Membre::selectRaw('statut, COUNT(*) as n')->groupBy('statut')->pluck('n', 'statut');
        $out = ['total' => (int) $parStatut->sum()];
        foreach (StatutMembre::cases() as $s) {
            $out[$s->value] = (int) ($parStatut[$s->value] ?? 0);
        }

        return $out;
    }

    /** Série mensuelle attendu / encaissé / impayés sur les $n dernières périodes (§7.4 graphiques). */
    public function serieCotisations(int $n = 12, ?Periode $fin = null): array
    {
        $fin ??= Periode::courante();
        $debut = $fin->ajouterMois(-($n - 1));
        $today = today()->toDateString();

        $rows = Cotisation::whereBetween('periode', [(string) $debut, (string) $fin])
            ->selectRaw("periode,
                SUM(CASE WHEN statut <> 'annule' THEN montant_attendu ELSE 0 END) as attendu,
                SUM(CASE WHEN statut <> 'annule' THEN montant_paye ELSE 0 END) as encaisse,
                SUM(CASE WHEN statut IN ('a_payer','partiel') AND date_echeance < ? THEN 1 ELSE 0 END) as impayes,
                SUM(CASE WHEN statut = 'paye_retard' THEN 1 ELSE 0 END) as retards", [$today])
            ->groupBy('periode')->get()->keyBy('periode');

        return collect(Periode::plage($debut, $fin))->map(fn (Periode $p) => [
            'periode' => (string) $p,
            'libelle' => $p->libelleCourt(),
            'attendu' => (int) ($rows[(string) $p]->attendu ?? 0),
            'encaisse' => (int) ($rows[(string) $p]->encaisse ?? 0),
            'impayes' => (int) ($rows[(string) $p]->impayes ?? 0),
            'retards' => (int) ($rows[(string) $p]->retards ?? 0),
        ])->all();
    }

    /** Encaissements réels par mois de date de paiement (trésorerie). */
    public function serieEncaissements(int $n = 12): array
    {
        $fin = Periode::courante();
        $debut = $fin->ajouterMois(-($n - 1));
        $rows = Paiement::valides()
            ->whereBetween('date_paiement', [$debut->premierJour()->toDateString(), $fin->dernierJour()->toDateString()])
            ->get(['date_paiement', 'montant'])
            ->groupBy(fn ($p) => $p->date_paiement->format('Y-m'))
            ->map->sum('montant');

        return collect(Periode::plage($debut, $fin))->map(fn (Periode $p) => [
            'periode' => (string) $p,
            'libelle' => $p->libelleCourt(),
            'montant' => (int) ($rows[(string) $p] ?? 0),
        ])->all();
    }

    /** Nombre de membres en activité à la fin de chaque mois. */
    public function serieEffectifs(int $n = 12): array
    {
        $fin = Periode::courante();
        $membres = Membre::get(['date_adhesion', 'date_sortie', 'statut']);

        return collect(Periode::plage($fin->ajouterMois(-($n - 1)), $fin))->map(function (Periode $p) use ($membres) {
            $jour = $p->dernierJour();

            return [
                'periode' => (string) $p,
                'libelle' => $p->libelleCourt(),
                'effectif' => $membres->filter(fn ($m) => $m->date_adhesion->lte($jour)
                    && ($m->date_sortie === null || $m->date_sortie->gt($jour))
                    && $m->statut !== StatutMembre::Autre)->count(),
            ];
        })->all();
    }

    /** Solde de caisse = solde initial + cotisations encaissées + entrées − sorties (opérations valides). */
    public function soldeCaisse(?string $jusquAu = null): int
    {
        $jusquAu ??= today()->toDateString();
        $initial = (int) $this->parametres->get('solde_initial', 0);
        $cotisations = (int) Paiement::valides()->whereDate('date_paiement', '<=', $jusquAu)->sum('montant');
        $ops = OperationFinanciere::valides()->whereDate('date_operation', '<=', $jusquAu)
            ->selectRaw("SUM(CASE WHEN type='entree' THEN montant ELSE 0 END) as e, SUM(CASE WHEN type='sortie' THEN montant ELSE 0 END) as s")
            ->first();

        return $initial + $cotisations + (int) $ops->e - (int) $ops->s;
    }

    /** Situation financière sur un intervalle de dates (§19). */
    public function situationFinanciere(string $du, string $au): array
    {
        $veille = \Carbon\CarbonImmutable::parse($du)->subDay()->toDateString();
        $cotisations = (int) Paiement::valides()->whereBetween('date_paiement', [$du, $au])->sum('montant');
        $parCategorie = OperationFinanciere::valides()
            ->whereBetween('date_operation', [$du, $au])
            ->join('categories', 'categories.id', '=', 'operations_financieres.categorie_id')
            ->groupBy('operations_financieres.type', 'categories.nom')
            ->selectRaw('operations_financieres.type as type, categories.nom as categorie, SUM(operations_financieres.montant) as total, COUNT(*) as nombre')
            ->orderBy('categories.nom')
            ->get();
        $entrees = (int) $parCategorie->where('type', 'entree')->sum('total');
        $sorties = (int) $parCategorie->where('type', 'sortie')->sum('total');
        $ouverture = $this->soldeCaisse($veille);

        return [
            'du' => $du,
            'au' => $au,
            'solde_ouverture' => $ouverture,
            'cotisations' => $cotisations,
            'entrees_par_categorie' => $parCategorie->where('type', 'entree')->values(),
            'sorties_par_categorie' => $parCategorie->where('type', 'sortie')->values(),
            'total_entrees' => $cotisations + $entrees,
            'total_sorties' => $sorties,
            'solde_cloture' => $ouverture + $cotisations + $entrees - $sorties,
        ];
    }

    /** Statistiques individuelles d'un membre (§7.3). */
    public function membre(Membre $membre): array
    {
        $today = today()->toDateString();
        $r = Cotisation::where('membre_id', $membre->id)
            ->selectRaw("
                SUM(CASE WHEN statut IN ('paye','paye_retard') THEN 1 ELSE 0 END) as mois_payes,
                SUM(CASE WHEN statut = 'paye_retard' THEN 1 ELSE 0 END) as retards,
                SUM(CASE WHEN statut IN ('a_payer','partiel') AND date_echeance < ? THEN 1 ELSE 0 END) as impayes,
                SUM(CASE WHEN statut IN ('a_payer','partiel') AND date_echeance < ? THEN montant_attendu - montant_paye ELSE 0 END) as du_echu,
                SUM(CASE WHEN statut IN ('a_payer','partiel') AND date_echeance >= ? THEN montant_attendu - montant_paye ELSE 0 END) as du_a_venir,
                SUM(CASE WHEN statut = 'regularise' THEN 1 ELSE 0 END) as regularises,
                COUNT(*) as total
            ", [$today, $today, $today])->first();

        return [
            'mois_payes' => (int) $r->mois_payes,
            'retards' => (int) $r->retards,
            'impayes' => (int) $r->impayes,
            'regularises' => (int) $r->regularises,
            'total_verse' => (int) Paiement::valides()->where('membre_id', $membre->id)->sum('montant'),
            'solde_du' => (int) $r->du_echu,
            'du_a_venir' => (int) $r->du_a_venir,
            'total' => (int) $r->total,
        ];
    }

    public function demandesAnnulationEnAttente(): int
    {
        return Paiement::enAttenteAnnulation()->count()
            + OperationFinanciere::valides()->whereNotNull('demande_annulation_le')->count();
    }

    /** Montant total restant dû sur les cotisations échues (toutes périodes). */
    public function totalImpayes(): array
    {
        $r = Cotisation::impayees()->selectRaw('COUNT(*) as n, COUNT(DISTINCT membre_id) as membres, SUM(montant_attendu - montant_paye) as montant')->first();

        return ['nombre' => (int) $r->n, 'membres' => (int) $r->membres, 'montant' => (int) $r->montant];
    }
}
