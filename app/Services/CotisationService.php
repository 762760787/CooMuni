<?php

namespace App\Services;

use App\Enums\StatutCotisation;
use App\Enums\StatutMembre;
use App\Exceptions\RegleMetierException;
use App\Models\Cotisation;
use App\Models\Membre;
use App\Support\Periode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Règles des cotisations mensuelles (§7.2, §8). Voir ASSUMPTIONS.md pour les
 * décisions prises sur les cas « à valider » (adhésion en cours de mois,
 * sortie, suspension, changement de montant...).
 */
class CotisationService
{
    public function __construct(private Parametres $parametres) {}

    public function periodeDebut(): Periode
    {
        return Periode::fromString((string) $this->parametres->requis('cotisation_periode_debut'));
    }

    public function echeance(Periode $periode): CarbonImmutable
    {
        return $periode->echeance($this->parametres->jourEcheance());
    }

    /** Première période due selon la règle d'adhésion paramétrée (§8.2 « adhésion en cours de mois »). */
    public function premierePeriodeDue(Membre $membre): Periode
    {
        $adhesion = CarbonImmutable::parse($membre->date_adhesion);
        $pAdhesion = Periode::fromDate($adhesion);

        $premiere = match ($this->parametres->get('regle_adhesion', 'echeance')) {
            'mois_complet' => $pAdhesion,
            'mois_suivant' => $pAdhesion->suivante(),
            default => $adhesion->lte($this->echeance($pAdhesion)) ? $pAdhesion : $pAdhesion->suivante(),
        };

        $debut = $this->periodeDebut();

        return $premiere->compare($debut) < 0 ? $debut : $premiere;
    }

    /** Dernière période due pour un membre sorti ou décédé (§8.2 « sortie d'un membre »), sinon null. */
    public function dernierePeriodeDue(Membre $membre): ?Periode
    {
        if (! $membre->date_sortie) {
            return null;
        }
        $sortie = CarbonImmutable::parse($membre->date_sortie);
        $pSortie = Periode::fromDate($sortie);

        // Redevable du mois de sortie seulement s'il était encore membre à la date d'échéance.
        return $sortie->gte($this->echeance($pSortie)) ? $pSortie : $pSortie->precedente();
    }

    public function statutRedevable(StatutMembre $statut): bool
    {
        return match ($statut) {
            StatutMembre::Actif, StatutMembre::Sorti, StatutMembre::Decede => true,
            StatutMembre::Suspendu => $this->parametres->bool('suspendu_redevable'),
            StatutMembre::Autre => false,
        };
    }

    public function estRedevable(Membre $membre, Periode $periode): bool
    {
        if (! $this->statutRedevable($membre->statut)) {
            return false;
        }
        if ($periode->compare($this->premierePeriodeDue($membre)) < 0) {
            return false;
        }
        $derniere = $this->dernierePeriodeDue($membre);

        return $derniere === null || $periode->compare($derniere) <= 0;
    }

    /**
     * Génère (de façon idempotente) les cotisations d'une période pour tous les membres redevables.
     * Le montant attendu est figé à la génération : un changement ultérieur du montant
     * paramétré ne s'applique qu'aux périodes futures (§8.2).
     */
    public function genererPeriode(Periode $periode, bool $auditer = true): int
    {
        $existants = Cotisation::where('periode', (string) $periode)->pluck('membre_id')->flip();

        $candidats = Membre::query()
            ->whereDate('date_adhesion', '<=', $periode->dernierJour())
            ->where(fn ($q) => $q->whereNull('date_sortie')->orWhereDate('date_sortie', '>=', $periode->premierJour()))
            ->get()
            ->reject(fn (Membre $m) => $existants->has($m->id))
            ->filter(fn (Membre $m) => $this->estRedevable($m, $periode));

        if ($candidats->isEmpty()) {
            return 0;
        }

        $montant = $this->parametres->montantCotisation();
        $echeance = $this->echeance($periode)->toDateString();
        $now = now();

        $lignes = $candidats->map(fn (Membre $m) => [
            'membre_id' => $m->id,
            'periode' => (string) $periode,
            'annee' => $periode->annee,
            'mois' => $periode->mois,
            'montant_attendu' => $montant,
            'montant_paye' => 0,
            'date_echeance' => $echeance,
            'statut' => StatutCotisation::APayer->value,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values();

        $crees = 0;
        foreach ($lignes->chunk(500) as $lot) {
            $crees += Cotisation::insertOrIgnore($lot->all());
        }

        if ($auditer && $crees > 0) {
            Audit::log('cotisation.generer', 'Cotisation', null, [
                'periode' => (string) $periode, 'nombre' => $crees, 'montant_unitaire' => $montant,
            ], "Génération de {$crees} cotisation(s) pour ".$periode->libelle());
        }

        return $crees;
    }

    /** Génère toutes les périodes manquantes depuis la période de début jusqu'à $fin (rattrapage). */
    public function genererJusqua(?Periode $fin = null): int
    {
        $fin ??= Periode::courante();
        $debut = $this->periodeDebut();
        if ($fin->compare($debut) < 0) {
            return 0;
        }

        $total = 0;
        foreach (Periode::plage($debut, $fin) as $periode) {
            $total += $this->genererPeriode($periode);
        }

        return $total;
    }

    /** Génère les cotisations d'un membre de sa première période due jusqu'au mois courant (§27.2). */
    public function genererPourMembre(Membre $membre, ?Periode $fin = null): int
    {
        $fin ??= Periode::courante();
        $debut = $this->premierePeriodeDue($membre);
        if ($fin->compare($debut) < 0) {
            return 0;
        }
        $crees = 0;
        foreach (Periode::plage($debut, $fin) as $periode) {
            if ($this->estRedevable($membre, $periode)) {
                $crees += $this->creerSiAbsente($membre, $periode) ? 1 : 0;
            }
        }

        return $crees;
    }

    /** Retourne la cotisation d'une période, en la créant si le membre en est redevable. */
    public function obtenir(Membre $membre, Periode $periode): ?Cotisation
    {
        $existante = Cotisation::where('membre_id', $membre->id)->where('periode', (string) $periode)->first();
        if ($existante) {
            return $existante;
        }
        if (! $this->estRedevable($membre, $periode)) {
            return null;
        }
        $this->creerSiAbsente($membre, $periode);

        return Cotisation::where('membre_id', $membre->id)->where('periode', (string) $periode)->first();
    }

    private function creerSiAbsente(Membre $membre, Periode $periode): bool
    {
        return Cotisation::insertOrIgnore([
            'membre_id' => $membre->id,
            'periode' => (string) $periode,
            'annee' => $periode->annee,
            'mois' => $periode->mois,
            'montant_attendu' => $this->parametres->montantCotisation(),
            'montant_paye' => 0,
            'date_echeance' => $this->echeance($periode)->toDateString(),
            'statut' => StatutCotisation::APayer->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]) > 0;
    }

    /**
     * Recalcule montant payé, date et statut à partir des paiements VALIDES ventilés.
     * Payé à temps si le règlement qui solde la cotisation est daté au plus tard
     * du jour d'échéance ; sinon « Payé en retard » (§8.1).
     */
    public function recalculer(Cotisation $cotisation): Cotisation
    {
        $imputations = DB::table('paiement_cotisation as pc')
            ->join('paiements as p', 'p.id', '=', 'pc.paiement_id')
            ->where('pc.cotisation_id', $cotisation->id)
            ->where('p.statut', 'valide')
            ->orderBy('p.date_paiement')->orderBy('p.id')
            ->get(['pc.montant', 'p.date_paiement']);

        $cumul = 0;
        $dateSolde = null;
        foreach ($imputations as $imp) {
            $cumul += (int) $imp->montant;
            if ($dateSolde === null && $cumul >= $cotisation->montant_attendu) {
                $dateSolde = $imp->date_paiement;
            }
        }
        $derniereDate = $imputations->last()?->date_paiement;

        $cotisation->montant_paye = $cumul;
        $cotisation->date_paiement = $dateSolde ?? $derniereDate;

        // Une cotisation annulée ou régularisée garde ce statut (décision administrative tracée).
        if (! in_array($cotisation->statut, [StatutCotisation::Annule, StatutCotisation::Regularise], true)) {
            $cotisation->statut = match (true) {
                $cumul >= $cotisation->montant_attendu && $cotisation->montant_attendu > 0 => CarbonImmutable::parse($dateSolde)->lte($cotisation->date_echeance)
                    ? StatutCotisation::Paye
                    : StatutCotisation::PayeRetard,
                $cumul > 0 => StatutCotisation::Partiel,
                default => StatutCotisation::APayer,
            };
        }
        $cotisation->save();

        return $cotisation;
    }

    /** Annule une cotisation émise à tort (aucun montant ne doit y être imputé). */
    public function annuler(Cotisation $cotisation, string $motif): void
    {
        $this->exigerMotif($motif);
        if ($cotisation->montant_paye > 0) {
            throw new RegleMetierException('Cette cotisation a reçu des paiements : annulez d\'abord les paiements concernés.');
        }
        if (! $cotisation->statut->estDue()) {
            throw new RegleMetierException('Seule une cotisation non réglée peut être annulée.');
        }
        $this->changerStatut($cotisation, StatutCotisation::Annule, $motif, 'cotisation.annuler');
    }

    /** Solde administrativement une cotisation (exonération, décès...) sans encaissement. */
    public function regulariser(Cotisation $cotisation, string $motif): void
    {
        $this->exigerMotif($motif);
        if (! $cotisation->statut->estDue()) {
            throw new RegleMetierException('Seule une cotisation non soldée peut être régularisée.');
        }
        $this->changerStatut($cotisation, StatutCotisation::Regularise, $motif, 'cotisation.regulariser');
    }

    /** Rétablit une cotisation annulée ou régularisée par erreur (statut recalculé). */
    public function retablir(Cotisation $cotisation, string $motif): void
    {
        $this->exigerMotif($motif);
        if (! in_array($cotisation->statut, [StatutCotisation::Annule, StatutCotisation::Regularise], true)) {
            throw new RegleMetierException('Seule une cotisation annulée ou régularisée peut être rétablie.');
        }
        $avant = ['statut' => $cotisation->statut->value, 'motif' => $cotisation->motif];
        $cotisation->statut = StatutCotisation::APayer;
        $cotisation->motif = 'Rétablie : '.$motif;
        $cotisation->traite_par = Auth::id();
        $cotisation->traite_le = now();
        $this->recalculer($cotisation);
        Audit::log('cotisation.retablir', $cotisation, $avant, ['statut' => $cotisation->statut->value, 'motif' => $motif],
            $cotisation->membre->nom_complet.' — '.$cotisation->periode_libelle);
    }

    /**
     * Après une sortie ou un décès : annule (avec trace) les cotisations postérieures
     * à la dernière période due qui n'ont reçu aucun paiement.
     *
     * @return int nombre de cotisations annulées
     */
    public function appliquerFinAdhesion(Membre $membre): int
    {
        $derniere = $this->dernierePeriodeDue($membre);
        if (! $derniere) {
            return 0;
        }
        $n = 0;
        Cotisation::where('membre_id', $membre->id)
            ->where('periode', '>', (string) $derniere)
            ->whereIn('statut', StatutCotisation::dues())
            ->where('montant_paye', 0)
            ->get()
            ->each(function (Cotisation $c) use ($membre, &$n) {
                $this->changerStatut($c, StatutCotisation::Annule,
                    'Période postérieure à la fin d\'adhésion ('.$membre->statut->label().' le '.$membre->date_sortie->format('d/m/Y').')',
                    'cotisation.annuler');
                $n++;
            });

        return $n;
    }

    private function changerStatut(Cotisation $c, StatutCotisation $statut, string $motif, string $action): void
    {
        $avant = ['statut' => $c->statut->value, 'motif' => $c->motif];
        $c->update([
            'statut' => $statut,
            'motif' => $motif,
            'traite_par' => Auth::id(),
            'traite_le' => now(),
        ]);
        Audit::log($action, $c, $avant, ['statut' => $statut->value, 'motif' => $motif],
            $c->membre->nom_complet.' — '.$c->periode_libelle);
    }

    private function exigerMotif(string $motif): void
    {
        if (mb_strlen(trim($motif)) < 5) {
            throw new RegleMetierException('Un motif explicite (5 caractères minimum) est obligatoire.');
        }
    }
}
