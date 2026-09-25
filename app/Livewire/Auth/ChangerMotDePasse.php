<?php

namespace App\Livewire\Auth;

use App\Services\Audit;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Changement de mot de passe imposé (mot de passe temporaire remis par un administrateur). */
#[Layout('layouts::guest')]
#[Title('Nouveau mot de passe')]
class ChangerMotDePasse extends Component
{
    public string $actuel = '';
    public string $nouveau = '';
    public string $nouveau_confirmation = '';

    public function mount()
    {
        if (! auth()->user()->doit_changer_mdp) {
            return $this->redirectRoute('profil');
        }
    }

    public function enregistrer()
    {
        $user = auth()->user();
        $this->validate([
            'actuel' => ['required', 'current_password'],
            'nouveau' => ['required', 'confirmed', 'different:actuel', Password::defaults()],
        ], [], ['actuel' => 'mot de passe temporaire', 'nouveau' => 'nouveau mot de passe']);

        $user->update(['password' => Hash::make($this->nouveau), 'doit_changer_mdp' => false]);
        session()->regenerate();
        Audit::log('auth.mdp_modifie', $user, null, null, 'Changement du mot de passe temporaire');
        session()->flash('succes', 'Mot de passe enregistré. Bienvenue !');

        return $this->redirectRoute('dashboard');
    }

    public function render()
    {
        return view('livewire.auth.changer-mot-de-passe');
    }
}
