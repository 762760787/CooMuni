<?php

namespace App\Livewire\Paiements;

use App\Livewire\Concerns\AvecRetours;
use App\Models\Membre;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Services\PaiementService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Écran 8 — Enregistrement d'un paiement (§9.1, §27.1).
 * Un paiement peut couvrir plusieurs mois (ventilation chronologique) ou une partie d'un mois.
 */
#[Title('Enregistrer un paiement')]
class Enregistrer extends Component
{
    use AvecRetours;

    #[Url(as: 'membre')]
    public ?int $membreId = null;

    #[Url(as: 'corrige')]
    public ?int $corrigePaiementId = null;

    public string $rechercheMembre = '';
    public array $periodes = [];
    public string $montant = '';
    public string $modeId = '';
    public string $datePaiement = '';
    public string $reference = '';
    public string $note = '';
    public bool $confirmerDoublon = false;
    public array $avertissements = [];
    public bool $montantModifie = false;

    public function mount(): void
    {
        $this->exiger('paiements.creer');
        $this->datePaiement = today()->toDateString();
        $this->modeId = (string) (ModePaiement::actifs()->value('id') ?? '');
        if ($this->corrigePaiementId) {
            $origine = Paiement::find($this->corrigePaiementId);
            if (! $origine || ! $origine->estAnnule()) {
                $this->corrigePaiementId = null;
            } else {
                $this->membreId ??= $origine->membre_id;
                $this->note = 'Correction du reçu '.$origine->numero_recu;
            }
        }
        if ($this->membreId) {
            $this->choisirMembre($this->membreId);
        }
    }

    public function choisirMembre(int $id): void
    {
        $membre = Membre::find($id);
        if (! $membre) {
            return;
        }
        $this->membreId = $membre->id;
        $this->rechercheMembre = '';
        $this->montantModifie = false;
        $this->avertissements = [];
        // Pré-sélection : la plus ancienne période due (ou le prochain mois).
        $reglables = app(PaiementService::class)->periodesReglables($membre);
        $this->periodes = $reglables ? [$reglables[0]['periode']] : [];
        $this->recalculerMontant();
    }

    public function changerMembre(): void
    {
        $this->reset('membreId', 'periodes', 'montant', 'avertissements', 'confirmerDoublon');
    }

    public function updatedPeriodes(): void
    {
        $this->montantModifie = false;
        $this->recalculerMontant();
    }

    public function updatedMontant(): void
    {
        $this->montantModifie = true;
        $this->avertissements = [];
        $this->confirmerDoublon = false;
    }

    private function recalculerMontant(): void
    {
        if ($this->montantModifie || ! $this->membreId) {
            return;
        }
        $reglables = collect(app(PaiementService::class)->periodesReglables(Membre::find($this->membreId)))->keyBy('periode');
        $this->montant = (string) collect($this->periodes)->sum(fn ($p) => $reglables[$p]['reste'] ?? 0);
    }

    public function enregistrer(PaiementService $service)
    {
        $this->exiger('paiements.creer');
        $this->validate([
            'membreId' => ['required', 'exists:membres,id'],
            'periodes' => ['required', 'array', 'min:1'],
            'periodes.*' => ['regex:/^\d{4}-\d{2}$/'],
            'montant' => ['required', 'integer', 'min:1'],
            'modeId' => ['required', 'exists:modes_paiement,id'],
            'datePaiement' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['periodes.required' => 'Sélectionnez au moins une période.'], [
            'membreId' => 'membre', 'modeId' => 'mode de paiement', 'datePaiement' => 'date de paiement',
        ]);

        if (! $this->confirmerDoublon) {
            $this->avertissements = $service->detecterDoublons($this->membreId, (int) $this->montant, $this->datePaiement, $this->reference ?: null)['avertissements'];
            if ($this->avertissements) {
                return;
            }
        }

        $paiement = $this->tenter(fn () => $service->enregistrer([
            'membre_id' => $this->membreId,
            'periodes' => $this->periodes,
            'montant' => (int) $this->montant,
            'date_paiement' => $this->datePaiement,
            'mode_paiement_id' => (int) $this->modeId,
            'reference' => $this->reference ?: null,
            'note' => $this->note ?: null,
            'confirmer_doublon' => $this->confirmerDoublon,
            'corrige_paiement_id' => $this->corrigePaiementId,
        ]), 'montant');

        if (! $paiement) {
            return;
        }
        session()->flash('succes', "Paiement enregistré — reçu {$paiement->numero_recu}.");

        return $this->redirectRoute('paiements.show', ['paiement' => $paiement, 'nouveau' => 1]);
    }

    public function render(PaiementService $service)
    {
        $membre = $this->membreId ? Membre::find($this->membreId) : null;
        $resultats = (! $membre && mb_strlen(trim($this->rechercheMembre)) >= 2)
            ? Membre::recherche($this->rechercheMembre)->orderBy('nom')->limit(8)->get()
            : collect();
        $modes = ModePaiement::actifs()->get();

        return view('livewire.paiements.enregistrer', [
            'membre' => $membre,
            'resultats' => $resultats,
            'reglables' => $membre ? $service->periodesReglables($membre) : [],
            'modes' => $modes,
            'modeCourant' => $modes->firstWhere('id', (int) $this->modeId),
            'partielAutorise' => (bool) parametre('paiement_partiel_autorise'),
            'origine' => $this->corrigePaiementId ? Paiement::find($this->corrigePaiementId) : null,
        ]);
    }
}
