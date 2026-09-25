<?php

namespace App\Livewire;

use App\Livewire\Concerns\AvecRetours;
use App\Services\Audit;
use App\Services\MembreService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Écran 19 — Profil utilisateur : coordonnées, mot de passe, préférences. */
#[Title('Mon profil')]
class Profil extends Component
{
    use AvecRetours;

    public string $email = '';
    public string $telephone = '';
    public bool $recevoirRappels = true;

    public string $actuel = '';
    public string $nouveau = '';
    public string $nouveau_confirmation = '';

    public function mount(): void
    {
        $u = Auth::user();
        $this->email = (string) $u->email;
        $this->telephone = (string) $u->telephone;
        $this->recevoirRappels = (bool) $u->preference('recevoir_rappels', true);
    }

    public function enregistrerCoordonnees(): void
    {
        $u = Auth::user();
        $data = $this->validate([
            'email' => ['nullable', 'email:rfc', 'max:150', Rule::unique('users', 'email')->ignore($u->id)],
            'telephone' => ['nullable', 'string', 'regex:/^\+?[\d\s.\-]{7,20}$/', Rule::unique('users', 'telephone')->ignore($u->id)],
            'recevoirRappels' => ['boolean'],
        ], [], ['telephone' => 'téléphone']);

        $valeurs = [
            'email' => $data['email'] ? mb_strtolower($data['email']) : null,
            'telephone' => MembreService::normaliserTelephone($data['telephone']),
        ];
        [$a, $b] = Audit::diff($u->only(['email', 'telephone']), $valeurs);
        $u->update($valeurs + ['preferences' => array_merge($u->preferences ?? [], ['recevoir_rappels' => $this->recevoirRappels])]);
        if ($b) {
            Audit::log('utilisateur.modifier', $u, $a, $b, 'Mise à jour de ses coordonnées');
        }
        $this->succes('Profil enregistré.');
    }

    public function changerMotDePasse(): void
    {
        $this->validate([
            'actuel' => ['required', 'current_password'],
            'nouveau' => ['required', 'confirmed', 'different:actuel', Password::defaults()],
        ], [], ['actuel' => 'mot de passe actuel', 'nouveau' => 'nouveau mot de passe']);

        $u = Auth::user();
        $u->update(['password' => Hash::make($this->nouveau)]);
        // Déconnecte les autres appareils.
        DB::table('sessions')->where('user_id', $u->id)->where('id', '!=', session()->getId())->delete();
        Audit::log('auth.mdp_modifie', $u);
        $this->reset('actuel', 'nouveau', 'nouveau_confirmation');
        $this->succes('Mot de passe modifié. Vos autres sessions ont été fermées.');
    }

    public function render()
    {
        return view('livewire.profil', ['user' => Auth::user()->load('membre', 'roles')]);
    }
}
