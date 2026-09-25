<?php

namespace App\Livewire\Paiements;

use App\Livewire\Concerns\AvecRetours;
use App\Models\AuditLog;
use App\Models\Paiement;
use App\Services\PaiementService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Écran 9 — Détail d'un paiement : reçu, annulation / correction tracée.
 * Workflow : le gestionnaire DEMANDE l'annulation (motif) ; l'administrateur
 * VALIDE ou REJETTE. L'administrateur peut aussi annuler directement (motif obligatoire).
 */
#[Title('Détail du paiement')]
class Detail extends Component
{
    use AvecRetours;

    public Paiement $paiement;

    #[Url]
    public bool $nouveau = false;

    public string $motif = '';
    public string $action = '';

    public function mount(Paiement $paiement): void
    {
        $this->exiger('paiements.voir');
    }

    public function ouvrir(string $action): void
    {
        abort_unless(in_array($action, ['demander', 'annuler', 'valider', 'rejeter'], true), 400);
        $this->exiger($action === 'demander' ? 'paiements.demander_annulation' : 'paiements.annuler');
        $this->action = $action;
        $this->motif = $action === 'valider' ? (string) $this->paiement->demande_annulation_motif : '';
        $this->resetErrorBag();
        $this->dispatch('ouvrir-modal', 'annulation');
    }

    public function confirmer(PaiementService $service): void
    {
        $this->exiger($this->action === 'demander' ? 'paiements.demander_annulation' : 'paiements.annuler');
        $this->validate(['motif' => ['required', 'string', 'min:5', 'max:500']]);

        $ok = $this->tenter(function () use ($service) {
            match ($this->action) {
                'demander' => $service->demanderAnnulation($this->paiement, $this->motif),
                'annuler', 'valider' => $service->annuler($this->paiement, $this->motif),
                'rejeter' => $service->rejeterDemande($this->paiement, $this->motif),
            };

            return true;
        }, 'motif');

        if ($ok) {
            $this->paiement->refresh();
            $this->dispatch('fermer-modal');
            $this->succes(match ($this->action) {
                'demander' => 'Demande d\'annulation transmise à l\'administrateur.',
                'rejeter' => 'Demande d\'annulation rejetée.',
                default => 'Paiement annulé. Les cotisations concernées ont été recalculées.',
            });
        }
    }

    public function render()
    {
        $p = $this->paiement->load([
            'membre', 'modePaiement', 'enregistrePar', 'annulePar', 'demandeAnnulationPar',
            'cotisations', 'paiementCorrige', 'correction',
        ]);

        return view('livewire.paiements.detail', [
            'trace' => AuditLog::with('user')->where('cible_type', 'Paiement')->where('cible_id', $p->id)->orderBy('date_action')->get(),
        ]);
    }
}
