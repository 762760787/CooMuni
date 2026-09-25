<?php

namespace App\Livewire;

use App\Livewire\Concerns\AvecRetours;
use App\Livewire\Paiements\Index as PaiementsIndex;
use App\Models\Paiement;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Écran 10 — Reçus : recherche, aperçu PDF, téléchargement.
 * « Selon rôle » : tous les reçus avec recus.voir, sinon uniquement ceux du membre connecté.
 */
class Recus extends Component
{
    use AvecRetours, WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(except: '')]
    public string $du = '';

    #[Url(except: '')]
    public string $au = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $user = auth()->user();
        $tous = $user->can('recus.voir');
        abort_unless($tous || ($user->can('espace.personnel') && $user->membre_id), 403);

        $q = Paiement::query()->when(! $tous, fn ($q) => $q->where('membre_id', $user->membre_id));
        $q = PaiementsIndex::filtrer($q, $tous ? $this->recherche : '', '', '', $this->du, $this->au)
            ->when(! $tous && $this->recherche, fn ($q) => $q->where('numero_recu', 'like', '%'.trim($this->recherche).'%'));

        return view('livewire.recus', [
            'tous' => $tous,
            'paiements' => $q->with(['membre', 'modePaiement', 'cotisations'])->latest('date_paiement')->latest('id')->paginate(20),
        ])->title($tous ? 'Reçus' : 'Mes reçus');
    }
}
