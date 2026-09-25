<?php

namespace App\Livewire\Paiements;

use App\Livewire\Concerns\AvecRetours;
use App\Models\Membre;
use App\Models\ModePaiement;
use App\Models\Paiement;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Journal des paiements (accès aux écrans 9 et 10). */
#[Title('Paiements')]
class Index extends Component
{
    use AvecRetours, WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(except: '')]
    public string $statut = '';

    #[Url(except: '')]
    public string $mode = '';

    #[Url(except: '')]
    public string $du = '';

    #[Url(except: '')]
    public string $au = '';

    public function updated($p): void
    {
        $this->resetPage();
    }

    public static function filtrer(Builder $q, string $recherche, string $statut, string $mode, string $du, string $au): Builder
    {
        $terme = trim($recherche);

        return $q
            ->when($terme !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('numero_recu', 'like', "%{$terme}%")
                ->orWhere('reference', 'like', "%{$terme}%")
                ->orWhereIn('membre_id', Membre::recherche($terme)->select('id'))))
            ->when($statut === 'valide', fn ($q) => $q->where('statut', 'valide')->whereNull('demande_annulation_le'))
            ->when($statut === 'annule', fn ($q) => $q->where('statut', 'annule'))
            ->when($statut === 'demande', fn ($q) => $q->enAttenteAnnulation())
            ->when($mode, fn ($q) => $q->where('mode_paiement_id', $mode))
            ->when($du, fn ($q) => $q->whereDate('date_paiement', '>=', $du))
            ->when($au, fn ($q) => $q->whereDate('date_paiement', '<=', $au));
    }

    public function render()
    {
        $this->exiger('paiements.voir');
        $base = self::filtrer(Paiement::query(), $this->recherche, $this->statut, $this->mode, $this->du, $this->au);

        return view('livewire.paiements.index', [
            'paiements' => (clone $base)->with(['membre', 'modePaiement', 'enregistrePar', 'cotisations'])
                ->latest('date_paiement')->latest('id')->paginate(20),
            'total' => (clone $base)->where('statut', 'valide')->sum('montant'),
            'modes' => ModePaiement::orderBy('ordre')->get(),
        ]);
    }
}
