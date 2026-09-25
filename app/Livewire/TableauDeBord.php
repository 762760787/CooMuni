<?php

namespace App\Livewire;

use App\Models\Cotisation;
use App\Models\Paiement;
use App\Services\Statistiques;
use App\Support\Periode;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Écran 2 — Tableau de bord (§7.4) : contenu adapté au rôle. */
#[Title('Tableau de bord')]
class TableauDeBord extends Component
{
    #[Url(as: 'p')]
    public string $periode = '';

    public int $nbMois = 6;

    public function mount(): void
    {
        if (! Periode::isValid($this->periode)) {
            $this->periode = (string) Periode::courante();
        }
    }

    public function changerPeriode(int $decalage): void
    {
        $this->periode = (string) Periode::fromString($this->periode)->ajouterMois($decalage);
    }

    public function render(Statistiques $stats)
    {
        $user = auth()->user();
        $periode = Periode::isValid($this->periode) ? Periode::fromString($this->periode) : Periode::courante();
        $data = ['periodeObj' => $periode, 'global' => null, 'perso' => null];

        if ($user->voitDonneesGlobales()) {
            $data['global'] = [
                'stats' => $stats->periode($periode),
                'effectifs' => $stats->effectifs(),
                'solde' => $stats->soldeCaisse(),
                'impayes' => $stats->totalImpayes(),
                'demandes' => $user->can('paiements.annuler') ? $stats->demandesAnnulationEnAttente() : 0,
                'serie' => $stats->serieCotisations($this->nbMois, $periode),
                'encaissements' => $stats->serieEncaissements($this->nbMois),
                'effectifSerie' => $stats->serieEffectifs($this->nbMois),
                'derniers' => $user->can('paiements.voir')
                    ? Paiement::with(['membre', 'modePaiement'])->latest('id')->limit(5)->get()
                    : collect(),
            ];
        }

        if ($user->membre_id && $user->can('espace.personnel')) {
            $membre = $user->membre;
            $data['perso'] = [
                'membre' => $membre,
                'stats' => $stats->membre($membre),
                'courante' => Cotisation::where('membre_id', $membre->id)->where('periode', (string) Periode::courante())->first(),
                'annee' => Cotisation::where('membre_id', $membre->id)->where('annee', Periode::courante()->annee)->get()->keyBy('mois'),
                'derniers' => Paiement::where('membre_id', $membre->id)->with('cotisations')->latest('date_paiement')->limit(3)->get(),
            ];
        }

        return view('livewire.tableau-de-bord', $data);
    }
}
