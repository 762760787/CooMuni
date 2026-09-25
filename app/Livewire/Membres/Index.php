<?php

namespace App\Livewire\Membres;

use App\Enums\StatutMembre;
use App\Livewire\Concerns\AvecRetours;
use App\Models\Membre;
use App\Support\Periode;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Écran 3 — Liste des membres : recherche, filtres, création, accès fiche. */
#[Title('Membres')]
class Index extends Component
{
    use AvecRetours, WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(except: '')]
    public string $statut = '';

    #[Url(except: '')]
    public string $service = '';

    #[Url(except: 'nom')]
    public string $tri = 'nom';

    public function updated($prop): void
    {
        if (in_array($prop, ['recherche', 'statut', 'service', 'tri'], true)) {
            $this->resetPage();
        }
    }

    public function reinitialiser(): void
    {
        $this->reset('recherche', 'statut', 'service', 'tri');
        $this->resetPage();
    }

    public function render()
    {
        $this->exiger('membres.voir');
        $courante = (string) Periode::courante();

        $membres = Membre::query()
            ->recherche($this->recherche)
            ->when($this->statut, fn ($q) => $q->where('statut', $this->statut))
            ->when($this->service, fn ($q) => $q->where('service', $this->service))
            ->with(['cotisations' => fn ($q) => $q->where('periode', $courante)])
            ->when($this->tri === 'matricule', fn ($q) => $q->orderBy('matricule'))
            ->when($this->tri === 'adhesion', fn ($q) => $q->orderByDesc('date_adhesion'))
            ->orderBy('nom')->orderBy('prenom')
            ->paginate(20);

        return view('livewire.membres.index', [
            'membres' => $membres,
            'services' => Membre::whereNotNull('service')->distinct()->orderBy('service')->pluck('service'),
            'statuts' => StatutMembre::options(),
            'periodeCourante' => Periode::courante(),
        ]);
    }
}
