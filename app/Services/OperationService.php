<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Categorie;
use App\Models\OperationFinanciere;
use App\Services\Notifications\Message;
use App\Services\Notifications\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Opérations de caisse hors cotisations (§7.8). Jamais supprimées : annulation tracée.
 */
class OperationService
{
    public function __construct(
        private Numerotation $numerotation,
        private NotificationService $notifications,
        private Statistiques $statistiques,
    ) {}

    /**
     * @param array{categorie_id:int, montant:int, date_operation:string, description:string,
     *              reference?:?string, mode_paiement_id?:?int} $data
     */
    public function creer(array $data, ?UploadedFile $justificatif = null): OperationFinanciere
    {
        $categorie = Categorie::findOrFail($data['categorie_id']);
        $user = Auth::user();

        if (! $categorie->actif) {
            throw new RegleMetierException('Cette catégorie est désactivée.');
        }
        if ($categorie->restreinte && ! $user?->can('operations.categories_restreintes')) {
            throw new RegleMetierException("La catégorie « {$categorie->nom} » est réservée aux administrateurs.");
        }
        if ((int) $data['montant'] <= 0) {
            throw new RegleMetierException('Le montant doit être supérieur à zéro.');
        }
        if (CarbonImmutable::parse($data['date_operation'])->isAfter(today())) {
            throw new RegleMetierException('La date de l\'opération ne peut pas être dans le futur.');
        }

        $chemin = null;
        $nomOrigine = null;
        if ($justificatif) {
            $chemin = $justificatif->storeAs('justificatifs/'.now()->format('Y/m'),
                Str::uuid().'.'.$justificatif->guessExtension(), 'local');
            $nomOrigine = Str::limit($justificatif->getClientOriginalName(), 200, '');
        }

        $operation = DB::transaction(fn () => OperationFinanciere::create([
            'numero' => $this->numerotation->suivant('operation', 'operation_prefixe'),
            'type' => $categorie->type,
            'categorie_id' => $categorie->id,
            'montant' => (int) $data['montant'],
            'date_operation' => $data['date_operation'],
            'description' => trim($data['description']),
            'reference' => $data['reference'] ?? null,
            'mode_paiement_id' => $data['mode_paiement_id'] ?? null,
            'justificatif' => $chemin,
            'justificatif_nom' => $nomOrigine,
            'enregistre_par' => $user->id,
            'statut' => 'valide',
        ]));

        Audit::log('operation.creer', $operation, null, [
            'numero' => $operation->numero,
            'type' => $operation->type,
            'categorie' => $categorie->nom,
            'montant' => $operation->montant,
            'date_operation' => $data['date_operation'],
            'description' => $operation->description,
        ], "{$operation->numero} — {$categorie->nom} — ".fcfa($operation->montant));

        return $operation;
    }

    /** Avertissement non bloquant si une sortie rend la caisse négative. */
    public function avertissementSolde(int $montant, string $type): ?string
    {
        if ($type !== 'sortie') {
            return null;
        }
        $solde = $this->statistiques->soldeCaisse();

        return $montant > $solde
            ? 'Attention : cette sortie dépasse le solde de caisse calculé ('.fcfa($solde).').'
            : null;
    }

    public function demanderAnnulation(OperationFinanciere $op, string $motif): void
    {
        $this->exigerMotif($motif);
        if ($op->estAnnule() || $op->annulationEnAttente()) {
            throw new RegleMetierException('Cette opération est déjà annulée ou en attente d\'annulation.');
        }
        $op->update(['demande_annulation_motif' => $motif, 'demande_annulation_par' => Auth::id(), 'demande_annulation_le' => now()]);
        Audit::log('operation.demande_annulation', $op, null, ['motif' => $motif], $op->numero);

        $this->notifications->envoyerAPermission('operations.annuler', new Message('demande_annulation',
            'Demande d\'annulation d\'opération',
            Auth::user()?->name." demande l'annulation de l'opération {$op->numero} (".fcfa($op->montant)."). Motif : {$motif}",
            route('operations.show', $op)));
    }

    public function rejeterDemande(OperationFinanciere $op, string $motif): void
    {
        $this->exigerMotif($motif);
        if (! $op->annulationEnAttente()) {
            throw new RegleMetierException('Aucune demande d\'annulation en attente.');
        }
        $demandeur = $op->demandeAnnulationPar;
        $avant = ['demande_annulation_motif' => $op->demande_annulation_motif];
        $op->update(['demande_annulation_motif' => null, 'demande_annulation_par' => null, 'demande_annulation_le' => null]);
        Audit::log('operation.rejet_annulation', $op, $avant, ['motif_rejet' => $motif], $op->numero);
        if ($demandeur) {
            $this->notifications->envoyer($demandeur, new Message('information', 'Demande d\'annulation rejetée',
                "Votre demande d'annulation de l'opération {$op->numero} a été rejetée. Motif : {$motif}", route('operations.show', $op)));
        }
    }

    public function annuler(OperationFinanciere $op, string $motif): void
    {
        $this->exigerMotif($motif);
        if ($op->estAnnule()) {
            throw new RegleMetierException('Cette opération est déjà annulée.');
        }
        $op->update(['statut' => 'annule', 'motif_annulation' => $motif, 'annule_par' => Auth::id(), 'annule_le' => now()]);
        Audit::log('operation.annuler', $op, ['statut' => 'valide'], ['statut' => 'annule', 'motif' => $motif],
            "{$op->numero} — ".fcfa($op->montant));
    }

    private function exigerMotif(string $motif): void
    {
        if (mb_strlen(trim($motif)) < 5) {
            throw new RegleMetierException('Un motif explicite (5 caractères minimum) est obligatoire.');
        }
    }
}
