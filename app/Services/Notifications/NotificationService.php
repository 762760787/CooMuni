<?php

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Support\Collection;

class NotificationService
{
    /** @return list<Canal> */
    protected function canaux(): array
    {
        // V2 : ajouter ici CanalEmail, puis CanalSms / CanalWhatsApp (V3).
        return array_values(array_filter([new CanalInterne], fn (Canal $c) => $c->estActif()));
    }

    /** @param User|iterable<User> $destinataires */
    public function envoyer(User|iterable $destinataires, Message $message): void
    {
        $destinataires = $destinataires instanceof User ? [$destinataires] : $destinataires;

        foreach ($destinataires as $user) {
            if (! $user->actif) {
                continue;
            }
            foreach ($this->canaux() as $canal) {
                $canal->envoyer($user, $message);
            }
        }
    }

    /** Utilisateurs actifs disposant d'une permission (ex. valider les annulations). */
    public function utilisateursAvecPermission(string $permission): Collection
    {
        return User::where('actif', true)->with('roles.permissions')->get()
            ->filter(fn (User $u) => $u->can($permission));
    }

    public function envoyerAPermission(string $permission, Message $message): void
    {
        $this->envoyer($this->utilisateursAvecPermission($permission), $message);
    }
}
