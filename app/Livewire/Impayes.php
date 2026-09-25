<?php

namespace App\Livewire;

use App\Livewire\Concerns\AvecRetours;
use App\Models\Cotisation;
use App\Models\Membre;
use App\Services\Audit;
use App\Services\Notifications\Message;
use App\Services\Notifications\NotificationService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Écran 11 — Impayés : cotisations non soldées dont l'échéance est dépassée (§7.5).
 * Relance : notification interne en V1 (SMS/WhatsApp prévus en V2/V3).
 */
#[Title('Impayés')]
class Impayes extends Component
{
    use AvecRetours, WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(except: '')]
    public string $service = '';

    #[Url(except: '')]
    public string $periode = '';

    #[Url(except: 0)]
    public int $minMois = 0;

    public function updated(): void
    {
        $this->resetPage();
    }

    public function relancer(int $membreId, NotificationService $notifications): void
    {
        $this->exiger('paiements.creer', 'cotisations.corriger');
        $membre = Membre::with('user')->findOrFail($membreId);
        if (! $membre->user?->actif) {
            $this->erreur('Ce membre n\'a pas de compte actif : relancez-le par téléphone.');

            return;
        }
        $du = Cotisation::impayees()->where('membre_id', $membre->id)->get();
        $notifications->envoyer($membre->user, new Message('retard', 'Rappel de cotisation',
            'Vous avez '.$du->count().' cotisation(s) impayée(s) pour un total de '.fcfa($du->sum('reste')).'. Merci de régulariser auprès du trésorier.',
            route('mon-historique'), 'relance:'.$membre->id.':'.now()->format('Y-m-d')));
        Audit::log('notification.diffuser', $membre, null, ['type' => 'relance_impaye', 'montant' => $du->sum('reste')], 'Relance de '.$membre->nom_complet);
        $this->succes('Rappel envoyé à '.$membre->nom_complet.' (notification interne).');
    }

    public function render()
    {
        $this->exiger('impayes.voir');

        $base = Cotisation::impayees()
            ->join('membres', 'membres.id', '=', 'cotisations.membre_id')
            ->when($this->periode, fn ($q) => $q->where('cotisations.periode', '<=', $this->periode))
            ->when($this->service, fn ($q) => $q->where('membres.service', $this->service))
            ->when($this->recherche, fn ($q) => $q->whereIn('membres.id', Membre::recherche($this->recherche)->select('id')));

        $groupes = (clone $base)
            ->groupBy('cotisations.membre_id')
            ->selectRaw('cotisations.membre_id, COUNT(*) as nb_mois, SUM(cotisations.montant_attendu - cotisations.montant_paye) as montant_du, MIN(cotisations.periode) as premiere, MAX(cotisations.periode) as derniere, MIN(membres.nom) as nom_tri')
            ->when($this->minMois > 0, fn ($q) => $q->havingRaw('COUNT(*) >= ?', [$this->minMois]))
            ->orderByDesc('nb_mois')->orderBy('nom_tri')
            ->paginate(20);

        $membres = Membre::with('user')->whereIn('id', $groupes->pluck('membre_id'))->get()->keyBy('id');
        $totaux = (clone $base)->selectRaw('COUNT(*) as n, COUNT(DISTINCT cotisations.membre_id) as m, SUM(cotisations.montant_attendu - cotisations.montant_paye) as t')->first();

        return view('livewire.impayes', [
            'groupes' => $groupes,
            'membres' => $membres,
            'totaux' => $totaux,
            'services' => Membre::whereNotNull('service')->distinct()->orderBy('service')->pluck('service'),
        ]);
    }
}
