<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\Audit;
use App\Services\Notifications\Message;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Mot de passe oublié (§8.2) : sans email/SMS en V1, la demande est transmise
 * aux administrateurs (notification interne), qui réinitialisent le mot de passe.
 * La réponse est identique que le compte existe ou non (pas d'énumération).
 */
#[Layout('layouts::guest')]
#[Title('Mot de passe oublié')]
class MotDePasseOublie extends Component
{
    public string $identifiant = '';
    public bool $envoye = false;

    public function demander(NotificationService $notifications): void
    {
        $this->validate(['identifiant' => 'required|string|max:120'], [], ['identifiant' => 'identifiant']);

        $cle = 'reinit:'.request()->ip();
        if (RateLimiter::tooManyAttempts($cle, 3)) {
            $this->addError('identifiant', 'Trop de demandes. Réessayez plus tard.');

            return;
        }
        RateLimiter::hit($cle, 3600);

        $saisie = trim($this->identifiant);
        $user = User::where('identifiant', $saisie)->orWhere('email', Str::lower($saisie))
            ->orWhere('telephone', preg_replace('/[^\d+]/', '', $saisie) ?: '—')->first();

        if ($user && $user->actif) {
            Audit::log('auth.demande_reinitialisation', $user, null, null, null, $user->id);
            $notifications->envoyerAPermission('utilisateurs.gerer', new Message(
                'demande_reinitialisation',
                'Demande de réinitialisation de mot de passe',
                "{$user->name} (identifiant : {$user->identifiant}) demande la réinitialisation de son mot de passe. Vérifiez l'identité de la personne avant de lui remettre un mot de passe temporaire.",
                route('utilisateurs').'?recherche='.urlencode($user->identifiant),
                'reinit:'.$user->id.':'.now()->format('Y-m-d'),
            ));
        }
        $this->envoye = true;
    }

    public function render()
    {
        return view('livewire.auth.mot-de-passe-oublie');
    }
}
