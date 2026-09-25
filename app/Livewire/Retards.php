<?php

namespace App\Livewire;

use App\Livewire\Concerns\AvecRetours;
use App\Models\Cotisation;
use App\Models\Membre;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Écran 12 — Retards : cotisations réglées APRÈS l'échéance (distinctes des impayés, §7.5),
 * avec l'historique des retards par membre.
 */
#[Title('Retards')]
class Retards extends Component
{
    use AvecRetours, WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(except: '')]
    public string $periode = '';

    #[Url(except: 'liste')]
    public string $vue = 'liste';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $this->exiger('retards.voir');
        $base = Cotisation::query()->where('cotisations.statut', 'paye_retard')
            ->when($this->periode, fn ($q) => $q->where('cotisations.periode', $this->periode))
            ->when($this->recherche, fn ($q) => $q->whereIn('cotisations.membre_id', Membre::recherche($this->recherche)->select('id')));

        $joursSql = DB::connection()->getDriverName() === 'sqlite'
            ? 'julianday(date_paiement) - julianday(date_echeance)'
            : 'DATEDIFF(date_paiement, date_echeance)';

        if ($this->vue === 'membres') {
            $lignes = (clone $base)->groupBy('membre_id')
                ->selectRaw("membre_id, COUNT(*) as nb, AVG({$joursSql}) as moyenne, MAX({$joursSql}) as maximum, MAX(periode) as derniere")
                ->orderByDesc('nb')->orderByDesc('moyenne')->paginate(20);
            $membres = Membre::whereIn('id', $lignes->pluck('membre_id'))->get()->keyBy('id');
        } else {
            $lignes = (clone $base)->with('membre')->orderByDesc('periode')->orderByDesc('date_paiement')->paginate(25);
            $membres = collect();
        }

        return view('livewire.retards', [
            'lignes' => $lignes,
            'membres' => $membres,
            'total' => (clone $base)->count(),
            'moyenne' => round((float) (clone $base)->selectRaw("AVG({$joursSql}) as m")->value('m'), 1),
        ]);
    }
}
