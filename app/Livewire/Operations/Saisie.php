<?php

namespace App\Livewire\Operations;

use App\Livewire\Concerns\AvecRetours;
use App\Models\Categorie;
use App\Models\ModePaiement;
use App\Services\OperationService;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Saisie d'une opération de caisse, avec justificatif optionnel (§7.8, §13 contrôle des fichiers). */
#[Title('Nouvelle opération')]
class Saisie extends Component
{
    use AvecRetours, WithFileUploads;

    public string $type = 'sortie';
    public string $categorieId = '';
    public string $montant = '';
    public string $dateOperation = '';
    public string $description = '';
    public string $reference = '';
    public string $modeId = '';
    public $justificatif = null;
    public bool $confirmerSolde = false;

    public function mount(): void
    {
        $this->exiger('operations.creer');
        $this->dateOperation = today()->toDateString();
    }

    public function updatedType(): void
    {
        $this->categorieId = '';
        $this->confirmerSolde = false;
    }

    public function enregistrer(OperationService $service)
    {
        $this->exiger('operations.creer');
        $this->validate([
            'type' => ['required', 'in:entree,sortie'],
            'categorieId' => ['required', 'exists:categories,id'],
            'montant' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'dateOperation' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'min:3', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:100'],
            'modeId' => ['nullable', 'exists:modes_paiement,id'],
            'justificatif' => ['nullable', 'file', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp', 'max:5120'],
        ], [], [
            'categorieId' => 'catégorie', 'dateOperation' => 'date', 'modeId' => 'mode de paiement',
        ]);

        $categorie = Categorie::find($this->categorieId);
        if ($categorie->type !== $this->type) {
            $this->addError('categorieId', 'Cette catégorie ne correspond pas au type choisi.');

            return;
        }
        $alerte = $service->avertissementSolde((int) $this->montant, $this->type);
        if ($alerte && ! $this->confirmerSolde) {
            $this->addError('confirmerSolde', $alerte.' Cochez la case pour confirmer.');

            return;
        }

        $op = $this->tenter(fn () => $service->creer([
            'categorie_id' => (int) $this->categorieId,
            'montant' => (int) $this->montant,
            'date_operation' => $this->dateOperation,
            'description' => $this->description,
            'reference' => $this->reference ?: null,
            'mode_paiement_id' => $this->modeId ? (int) $this->modeId : null,
        ], $this->justificatif), 'categorieId');

        if ($op) {
            session()->flash('succes', "Opération {$op->numero} enregistrée.");

            return $this->redirectRoute('operations.show', $op);
        }
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.operations.saisie', [
            'categories' => Categorie::actives()->where('type', $this->type)->orderBy('nom')->get()
                ->filter(fn ($c) => ! $c->restreinte || $user->can('operations.categories_restreintes')),
            'modes' => ModePaiement::actifs()->get(),
        ]);
    }
}
