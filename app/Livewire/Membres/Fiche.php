<?php

namespace App\Livewire\Membres;

use App\Enums\StatutMembre;
use App\Livewire\Concerns\AvecRetours;
use App\Models\Membre;
use App\Services\MembreService;
use App\Services\Statistiques;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Écran 4 — Fiche membre : informations, historique, statut, actions. */
#[Title('Fiche membre')]
class Fiche extends Component
{
    use AvecRetours;

    public Membre $membre;

    // Changement de statut
    public string $nouveauStatut = '';
    public string $dateEffet = '';
    public string $motif = '';

    // Mot de passe temporaire affiché une seule fois après création du compte
    public ?string $mdpTemporaire = null;

    public function mount(Membre $membre): void
    {
        $user = auth()->user();
        abort_unless($user->can('membres.voir') || $user->membre_id === $membre->id, 403);
        $this->dateEffet = today()->toDateString();
    }

    public function changerStatut(MembreService $service): void
    {
        $this->exiger('membres.changer_statut');
        $this->validate([
            'nouveauStatut' => ['required', 'in:'.implode(',', array_column(StatutMembre::cases(), 'value'))],
            'dateEffet' => ['required', 'date', 'before_or_equal:today'],
            'motif' => ['required', 'string', 'min:5', 'max:500'],
        ], [], ['nouveauStatut' => 'nouveau statut', 'dateEffet' => 'date d\'effet']);

        $ok = $this->tenter(function () use ($service) {
            $service->changerStatut($this->membre, StatutMembre::from($this->nouveauStatut), $this->dateEffet, $this->motif);

            return true;
        }, 'nouveauStatut');

        if ($ok) {
            $this->membre->refresh();
            $this->reset('nouveauStatut', 'motif');
            $this->dispatch('fermer-modal');
            $this->succes('Statut mis à jour.');
        }
    }

    public function creerCompte(MembreService $service): void
    {
        $this->exiger('utilisateurs.gerer');
        $r = $this->tenter(fn () => $service->creerCompte($this->membre));
        if ($r) {
            $this->mdpTemporaire = $r[1];
            $this->membre->load('user');
            $this->dispatch('ouvrir-modal', 'mdp-temporaire');
        }
    }

    public function render(Statistiques $stats)
    {
        $m = $this->membre->load(['user', 'historiqueStatuts.user', 'createur']);

        return view('livewire.membres.fiche', [
            'stats' => $stats->membre($m),
            'cotisations' => $m->cotisations()->orderByDesc('periode')->limit(6)->get(),
            'paiements' => $m->paiements()->with(['modePaiement', 'cotisations'])->latest('date_paiement')->latest('id')->limit(5)->get(),
            'statuts' => StatutMembre::options(),
            'peutGerer' => auth()->user()->can('membres.voir'),
        ]);
    }
}
