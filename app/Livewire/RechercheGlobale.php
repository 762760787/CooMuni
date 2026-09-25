<?php

namespace App\Livewire;

use App\Models\Membre;
use Livewire\Component;

/** Recherche globale des membres : nom, prénom, matricule, téléphone (§7.11, §11.2). */
class RechercheGlobale extends Component
{
    public string $terme = '';
    public bool $mobile = false;

    public function render()
    {
        $resultats = collect();
        if (mb_strlen(trim($this->terme)) >= 2 && auth()->user()->can('membres.voir')) {
            $resultats = Membre::recherche($this->terme)->orderBy('nom')->orderBy('prenom')->limit(8)->get();
        }

        return view('livewire.recherche-globale', compact('resultats'));
    }
}
