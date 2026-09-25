<?php

namespace App\Livewire\Operations;

use App\Livewire\Concerns\AvecRetours;
use App\Models\Categorie;
use App\Models\OperationFinanciere;
use App\Services\Statistiques;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Écran 13 — Opérations financières : entrées / sorties de caisse (§7.8). */
#[Title('Opérations financières')]
class Index extends Component
{
    use AvecRetours, WithPagination;

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $categorie = '';

    #[Url(except: '')]
    public string $statut = '';

    #[Url(except: '')]
    public string $du = '';

    #[Url(except: '')]
    public string $au = '';

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(Statistiques $stats)
    {
        $this->exiger('operations.voir');
        $terme = trim($this->recherche);
        $base = OperationFinanciere::query()
            ->when($this->type, fn ($q) => $q->where('type', $this->type))
            ->when($this->categorie, fn ($q) => $q->where('categorie_id', $this->categorie))
            ->when($this->statut === 'valide', fn ($q) => $q->where('statut', 'valide'))
            ->when($this->statut === 'annule', fn ($q) => $q->where('statut', 'annule'))
            ->when($this->statut === 'demande', fn ($q) => $q->where('statut', 'valide')->whereNotNull('demande_annulation_le'))
            ->when($this->du, fn ($q) => $q->whereDate('date_operation', '>=', $this->du))
            ->when($this->au, fn ($q) => $q->whereDate('date_operation', '<=', $this->au))
            ->when($terme !== '', fn ($q) => $q->where(fn ($q) => $q->where('description', 'like', "%{$terme}%")
                ->orWhere('numero', 'like', "%{$terme}%")->orWhere('reference', 'like', "%{$terme}%")));

        $valides = (clone $base)->where('statut', 'valide');

        return view('livewire.operations.index', [
            'operations' => (clone $base)->with(['categorie', 'enregistrePar'])->latest('date_operation')->latest('id')->paginate(20),
            'entrees' => (int) (clone $valides)->where('type', 'entree')->sum('montant'),
            'sorties' => (int) (clone $valides)->where('type', 'sortie')->sum('montant'),
            'solde' => $stats->soldeCaisse(),
            'categories' => Categorie::orderBy('type')->orderBy('nom')->get(),
        ]);
    }
}
