<?php

namespace App\Services;

use App\Enums\StatutCotisation;
use App\Exceptions\RegleMetierException;
use App\Models\Cotisation;
use App\Models\Imputation;
use App\Models\Membre;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Services\Notifications\Message;
use App\Services\Notifications\NotificationService;
use App\Support\Periode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Enregistrement, ventilation et annulation des paiements (§7.2, §7.6, §8.2).
 */
class PaiementService
{
    public function __construct(
        private Parametres $parametres,
        private CotisationService $cotisations,
        private Numerotation $numerotation,
        private NotificationService $notifications,
    ) {}

    /**
     * Périodes proposées au règlement pour un membre : cotisations dues (les plus
     * anciennes d'abord) puis mois futurs payables d'avance.
     *
     * @return list<array{periode:string, libelle:string, reste:int, etat:string, existe:bool, echue:bool}>
     */
    public function periodesReglables(Membre $membre): array
    {
        $dues = Cotisation::where('membre_id', $membre->id)->dues()->orderBy('periode')->get();
        $out = $dues->map(fn (Cotisation $c) => [
            'periode' => $c->periode,
            'libelle' => $c->periode_libelle,
            'reste' => $c->reste,
            'etat' => $c->etat()->label(),
            'existe' => true,
            'echue' => $c->estEchue(),
        ])->all();

        $maxAvance = (int) $this->parametres->get('paiement_avance_max_mois', 0);
        $existantes = Cotisation::where('membre_id', $membre->id)->pluck('periode')->flip();
        $p = Periode::courante();
        for ($i = 0; $i <= $maxAvance; $i++, $p = $p->suivante()) {
            if ($existantes->has((string) $p) || ! $this->cotisations->estRedevable($membre, $p)) {
                continue;
            }
            $out[] = [
                'periode' => (string) $p,
                'libelle' => $p->libelle(),
                'reste' => $this->parametres->montantCotisation(),
                'etat' => 'À venir (avance)',
                'existe' => false,
                'echue' => false,
            ];
        }

        usort($out, fn ($a, $b) => strcmp($a['periode'], $b['periode']));

        return $out;
    }

    /**
     * Contrôles anti-doublon (§8.2 « doublon de paiement »).
     *
     * @return array{bloquants: list<string>, avertissements: list<string>}
     */
    public function detecterDoublons(int $membreId, int $montant, string $date, ?string $reference): array
    {
        $bloquants = [];
        $avertissements = [];

        if ($reference !== null && trim($reference) !== '') {
            $existant = Paiement::valides()->where('reference', trim($reference))->first();
            if ($existant) {
                $bloquants[] = "La référence « {$reference} » est déjà utilisée par le reçu {$existant->numero_recu} ({$existant->membre->nom_complet}).";
            }
        }

        $fenetre = (int) $this->parametres->get('doublon_fenetre_jours', 0);
        $d = CarbonImmutable::parse($date);
        $proches = Paiement::valides()
            ->where('membre_id', $membreId)
            ->where('montant', $montant)
            ->whereBetween('date_paiement', [$d->subDays($fenetre)->toDateString(), $d->addDays($fenetre)->toDateString()])
            ->get();
        foreach ($proches as $p) {
            $avertissements[] = "Un paiement de ".fcfa($p->montant)." a déjà été enregistré pour ce membre le {$p->date_paiement->format('d/m/Y')} (reçu {$p->numero_recu}).";
        }

        return compact('bloquants', 'avertissements');
    }

    /**
     * @param array{membre_id:int, periodes:list<string>, montant:int, date_paiement:string,
     *              mode_paiement_id:int, reference?:?string, note?:?string,
     *              confirmer_doublon?:bool, corrige_paiement_id?:?int} $data
     */
    public function enregistrer(array $data): Paiement
    {
        $membre = Membre::findOrFail($data['membre_id']);
        $mode = ModePaiement::findOrFail($data['mode_paiement_id']);
        $montant = (int) $data['montant'];
        $date = CarbonImmutable::parse($data['date_paiement']);
        $reference = isset($data['reference']) && trim((string) $data['reference']) !== '' ? trim($data['reference']) : null;

        if (! $mode->actif) {
            throw new RegleMetierException('Ce mode de paiement est désactivé.');
        }
        if ($mode->reference_requise && $reference === null) {
            throw new RegleMetierException("Une référence de transaction est obligatoire pour le mode « {$mode->nom} ».");
        }
        if ($montant <= 0) {
            throw new RegleMetierException('Le montant doit être supérieur à zéro.');
        }
        if ($date->isAfter(today())) {
            throw new RegleMetierException('La date de paiement ne peut pas être dans le futur.');
        }
        $periodes = collect($data['periodes'] ?? [])->filter(fn ($p) => Periode::isValid($p))->unique()->sort()->values();
        if ($periodes->isEmpty()) {
            throw new RegleMetierException('Sélectionnez au moins une période à régler.');
        }

        $doublons = $this->detecterDoublons($membre->id, $montant, $date->toDateString(), $reference);
        if ($doublons['bloquants']) {
            throw new RegleMetierException(implode(' ', $doublons['bloquants']));
        }
        if ($doublons['avertissements'] && empty($data['confirmer_doublon'])) {
            throw new RegleMetierException('Doublon possible : '.implode(' ', $doublons['avertissements']).' Cochez la confirmation pour enregistrer malgré tout.');
        }

        $paiement = DB::transaction(function () use ($membre, $mode, $montant, $date, $reference, $periodes, $data) {
            // Cotisations concernées (créées si paiement d'avance), verrouillées pendant la ventilation.
            $ids = [];
            foreach ($periodes as $p) {
                $c = $this->cotisations->obtenir($membre, Periode::fromString($p));
                if (! $c) {
                    throw new RegleMetierException('Le membre n\'est pas redevable pour '.periode_libelle($p).'.');
                }
                $ids[] = $c->id;
            }
            $cotisations = Cotisation::whereIn('id', $ids)->orderBy('periode')->lockForUpdate()->get();

            $dues = $cotisations->filter(fn (Cotisation $c) => $c->statut->estDue() && $c->reste > 0);
            if ($dues->isEmpty()) {
                throw new RegleMetierException('Les périodes sélectionnées sont déjà réglées.');
            }
            $totalDu = $dues->sum('reste');
            if ($montant > $totalDu) {
                throw new RegleMetierException('Le montant ('.fcfa($montant).') dépasse le total dû pour les périodes sélectionnées ('.fcfa($totalDu).').');
            }
            if ($montant < $totalDu && ! $this->parametres->bool('paiement_partiel_autorise')) {
                throw new RegleMetierException('Le paiement partiel n\'est pas autorisé : le montant doit être de '.fcfa($totalDu).'.');
            }

            $paiement = Paiement::create([
                'numero_recu' => $this->numerotation->suivant('recu', 'recu_prefixe'),
                'membre_id' => $membre->id,
                'montant' => $montant,
                'date_paiement' => $date->toDateString(),
                'mode_paiement_id' => $mode->id,
                'reference' => $reference,
                'note' => $data['note'] ?? null,
                'enregistre_par' => Auth::id(),
                'statut' => 'valide',
                'corrige_paiement_id' => $data['corrige_paiement_id'] ?? null,
            ]);

            // Ventilation chronologique : la période la plus ancienne est soldée en premier.
            $restant = $montant;
            foreach ($dues as $c) {
                if ($restant <= 0) {
                    break;
                }
                $part = min($restant, $c->reste);
                Imputation::create(['paiement_id' => $paiement->id, 'cotisation_id' => $c->id, 'montant' => $part]);
                $restant -= $part;
                $this->cotisations->recalculer($c);
            }

            return $paiement;
        });

        $paiement->load('cotisations', 'modePaiement', 'membre');
        Audit::log('paiement.creer', $paiement, null, [
            'numero_recu' => $paiement->numero_recu,
            'membre' => $membre->matricule.' '.$membre->nom_complet,
            'montant' => $montant,
            'date_paiement' => $date->toDateString(),
            'mode' => $mode->nom,
            'reference' => $reference,
            'ventilation' => $paiement->cotisations->mapWithKeys(fn ($c) => [$c->periode => (int) $c->pivot->montant])->all(),
            'corrige_paiement_id' => $paiement->corrige_paiement_id,
        ], "Reçu {$paiement->numero_recu} — {$membre->nom_complet} — ".fcfa($montant));

        if ($membre->user) {
            $this->notifications->envoyer($membre->user, new Message(
                'confirmation_paiement',
                'Paiement enregistré',
                'Votre paiement de '.fcfa($montant).' ('.$paiement->libellePeriodes().') a été enregistré. Reçu n° '.$paiement->numero_recu.'.',
                route('recus.index'),
            ));
        }

        return $paiement;
    }

    /** Demande d'annulation par un gestionnaire : validée ensuite par un administrateur (§8.2). */
    public function demanderAnnulation(Paiement $paiement, string $motif): void
    {
        $this->exigerMotif($motif);
        if ($paiement->estAnnule()) {
            throw new RegleMetierException('Ce paiement est déjà annulé.');
        }
        if ($paiement->annulationEnAttente()) {
            throw new RegleMetierException('Une demande d\'annulation est déjà en attente pour ce paiement.');
        }
        $paiement->update([
            'demande_annulation_motif' => $motif,
            'demande_annulation_par' => Auth::id(),
            'demande_annulation_le' => now(),
        ]);
        Audit::log('paiement.demande_annulation', $paiement, null, ['motif' => $motif], "Reçu {$paiement->numero_recu}");

        $this->notifications->envoyerAPermission('paiements.annuler', new Message(
            'demande_annulation',
            'Demande d\'annulation de paiement',
            Auth::user()?->name." demande l'annulation du reçu {$paiement->numero_recu} (".fcfa($paiement->montant)."). Motif : {$motif}",
            route('paiements.show', $paiement),
        ));
    }

    public function rejeterDemande(Paiement $paiement, string $motif): void
    {
        $this->exigerMotif($motif);
        if (! $paiement->annulationEnAttente()) {
            throw new RegleMetierException('Aucune demande d\'annulation en attente.');
        }
        $demandeur = $paiement->demandeAnnulationPar;
        $avant = ['demande_annulation_motif' => $paiement->demande_annulation_motif, 'demande_annulation_par' => $paiement->demande_annulation_par];
        $paiement->update(['demande_annulation_motif' => null, 'demande_annulation_par' => null, 'demande_annulation_le' => null]);
        Audit::log('paiement.rejet_annulation', $paiement, $avant, ['motif_rejet' => $motif], "Reçu {$paiement->numero_recu}");

        if ($demandeur) {
            $this->notifications->envoyer($demandeur, new Message('information', 'Demande d\'annulation rejetée',
                "Votre demande d'annulation du reçu {$paiement->numero_recu} a été rejetée. Motif : {$motif}", route('paiements.show', $paiement)));
        }
    }

    /** Annulation effective (administrateur) : le paiement reste en base, marqué « Annulé » (§8.1). */
    public function annuler(Paiement $paiement, string $motif): void
    {
        $this->exigerMotif($motif);
        if ($paiement->estAnnule()) {
            throw new RegleMetierException('Ce paiement est déjà annulé.');
        }

        DB::transaction(function () use ($paiement, $motif) {
            $paiement->update([
                'statut' => 'annule',
                'motif_annulation' => $motif,
                'annule_par' => Auth::id(),
                'annule_le' => now(),
            ]);
            $ids = $paiement->imputations()->pluck('cotisation_id');
            Cotisation::whereIn('id', $ids)->lockForUpdate()->get()
                ->each(fn (Cotisation $c) => $this->cotisations->recalculer($c));
        });

        Audit::log('paiement.annuler', $paiement, ['statut' => 'valide'], [
            'statut' => 'annule', 'motif' => $motif, 'demande_par' => $paiement->demandeAnnulationPar?->name,
        ], "Reçu {$paiement->numero_recu} — ".fcfa($paiement->montant));

        $destinataires = collect([$paiement->membre->user, $paiement->demandeAnnulationPar, $paiement->enregistrePar])
            ->filter()->unique('id')->reject(fn ($u) => $u->id === Auth::id());
        $this->notifications->envoyer($destinataires, new Message('information', 'Paiement annulé',
            "Le reçu {$paiement->numero_recu} (".fcfa($paiement->montant).") a été annulé. Motif : {$motif}", route('recus.index')));
    }

    private function exigerMotif(string $motif): void
    {
        if (mb_strlen(trim($motif)) < 5) {
            throw new RegleMetierException('Un motif explicite (5 caractères minimum) est obligatoire.');
        }
    }
}
