<?php

namespace App\Livewire\Operations;

use App\Livewire\Concerns\AvecRetours;
use App\Models\AuditLog;
use App\Models\OperationFinanciere;
use App\Services\OperationService;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Détail d'une opération de caisse ; annulation tracée (jamais de suppression). */
#[Title('Détail de l\'opération')]
class Detail extends Component
{
    use AvecRetours;

    public OperationFinanciere $operation;
    public string $action = '';
    public string $motif = '';

    public function mount(OperationFinanciere $operation): void
    {
        $this->exiger('operations.voir');
    }

    public function ouvrir(string $action): void
    {
        abort_unless(in_array($action, ['demander', 'annuler', 'valider', 'rejeter'], true), 400);
        $this->exiger($action === 'demander' ? 'operations.demander_annulation' : 'operations.annuler');
        $this->action = $action;
        $this->motif = $action === 'valider' ? (string) $this->operation->demande_annulation_motif : '';
        $this->resetErrorBag();
        $this->dispatch('ouvrir-modal', 'annulation-op');
    }

    public function confirmer(OperationService $service): void
    {
        $this->exiger($this->action === 'demander' ? 'operations.demander_annulation' : 'operations.annuler');
        $this->validate(['motif' => ['required', 'string', 'min:5', 'max:500']]);
        $ok = $this->tenter(function () use ($service) {
            match ($this->action) {
                'demander' => $service->demanderAnnulation($this->operation, $this->motif),
                'annuler', 'valider' => $service->annuler($this->operation, $this->motif),
                'rejeter' => $service->rejeterDemande($this->operation, $this->motif),
            };

            return true;
        }, 'motif');
        if ($ok) {
            $this->operation->refresh();
            $this->dispatch('fermer-modal');
            $this->succes('Opération mise à jour (tracée dans le journal d\'audit).');
        }
    }

    public function render()
    {
        $this->operation->load(['categorie', 'modePaiement', 'enregistrePar', 'annulePar', 'demandeAnnulationPar']);

        return view('livewire.operations.detail', [
            'trace' => AuditLog::with('user')->where('cible_type', 'OperationFinanciere')->where('cible_id', $this->operation->id)->orderBy('date_action')->get(),
        ]);
    }
}
