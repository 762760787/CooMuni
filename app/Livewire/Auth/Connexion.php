<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Écran 1 — Connexion. Identifiant accepté : nom d'utilisateur/matricule,
 * email ou téléphone. Limitation des tentatives (§13 anti brute-force).
 */
#[Layout('layouts::guest')]
#[Title('Connexion')]
class Connexion extends Component
{
    public string $identifiant = '';
    public string $password = '';
    public bool $remember = false;

    private const MAX_TENTATIVES = 5;

    public function connecter()
    {
        $this->validate([
            'identifiant' => 'required|string|max:120',
            'password' => 'required|string|max:200',
        ], [], ['identifiant' => 'identifiant', 'password' => 'mot de passe']);

        $cle = 'connexion:'.Str::lower(trim($this->identifiant)).'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($cle, self::MAX_TENTATIVES) || RateLimiter::tooManyAttempts('connexion-ip:'.request()->ip(), 20)) {
            $secondes = max(RateLimiter::availableIn($cle), RateLimiter::availableIn('connexion-ip:'.request()->ip()));
            Audit::log('auth.blocage', 'User', null, ['identifiant' => $this->identifiant]);
            $this->addError('identifiant', "Trop de tentatives. Réessayez dans {$secondes} secondes.");

            return;
        }

        $saisie = trim($this->identifiant);
        $telephone = preg_replace('/[^\d+]/', '', $saisie);
        $user = User::where('identifiant', $saisie)
            ->orWhere('email', Str::lower($saisie))
            ->when(strlen($telephone) >= 7, fn ($q) => $q->orWhere('telephone', $telephone))
            ->first();

        if (! $user || ! Hash::check($this->password, $user->password)) {
            RateLimiter::hit($cle, 300);
            RateLimiter::hit('connexion-ip:'.request()->ip(), 300);
            Audit::log('auth.echec', 'User', null, ['identifiant' => $saisie], null, $user?->id);
            $this->addError('identifiant', 'Identifiant ou mot de passe incorrect.');
            $this->reset('password');

            return;
        }
        if (! $user->actif) {
            Audit::log('auth.echec', $user, null, ['motif' => 'compte désactivé'], null, $user->id);
            $this->addError('identifiant', 'Ce compte est désactivé. Contactez un administrateur.');

            return;
        }

        RateLimiter::clear($cle);
        Auth::login($user, $this->remember);
        session()->regenerate();
        $user->forceFill(['derniere_connexion_at' => now()])->save();
        Audit::log('auth.connexion', $user);

        return $this->redirectIntended(route($user->doit_changer_mdp ? 'password.change' : 'dashboard'));
    }

    public function render()
    {
        return view('livewire.auth.connexion');
    }
}
