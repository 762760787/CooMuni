<?php

namespace App\Livewire\Cotisations;

use App\Models\Cotisation;
use App\Models\Membre;
use App\Services\Statistiques;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Écran 7 — Historique des cotisations d'un membre : tableau mensuel et statistiques (§7.3, §9.2).
 * Sans paramètre : historique du membre connecté (« Mon historique »).
 */
class Historique extends Component
{
    public Membre $membre;
    public bool $personnel = false;

    #[Url]
    public ?int $annee = null;

    public function mount(?Membre $membre = null): void
    {
        $user = auth()->user();
        if ($membre?->exists) {
            abort_unless($user->can('cotisations.voir') || $user->membre_id === $membre->id, 403);
            $this->membre = $membre;
            $this->personnel = $user->membre_id === $membre->id;
        } else {
            abort_unless($user->membre_id && $user->can('espace.personnel'), 403);
            $this->membre = $user->membre;
            $this->personnel = true;
        }
        $this->annee ??= (int) now()->format('Y');
    }

    public function render(Statistiques $stats)
    {
        $annees = Cotisation::where('membre_id', $this->membre->id)->distinct()->orderByDesc('annee')->pluck('annee');
        if ($annees->isNotEmpty() && ! $annees->contains($this->annee)) {
            $this->annee = $annees->first();
        }

        $cotisations = Cotisation::where('membre_id', $this->membre->id)->where('annee', $this->annee)
            ->with(['paiements' => fn ($q) => $q->with('modePaiement')->orderBy('date_paiement')])
            ->orderBy('mois')->get();

        return view('livewire.cotisations.historique', [
            'stats' => $stats->membre($this->membre),
            'annees' => $annees,
            'cotisations' => $cotisations,
            'parMois' => $cotisations->keyBy('mois'),
        ])->title($this->personnel ? 'Mon historique' : 'Historique — '.$this->membre->nom_complet);
    }
}
