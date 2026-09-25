<?php

namespace App\Services\Notifications;

use App\Models\NotificationInterne;
use App\Models\User;

class CanalInterne implements Canal
{
    public function nom(): string
    {
        return 'interne';
    }

    public function estActif(): bool
    {
        return true;
    }

    public function envoyer(User $destinataire, Message $message): void
    {
        // insertOrIgnore + clé d'unicité : un même rappel n'est jamais envoyé deux fois.
        NotificationInterne::insertOrIgnore([
            'user_id' => $destinataire->id,
            'type' => $message->type,
            'canal' => $this->nom(),
            'titre' => $message->titre,
            'contenu' => $message->contenu,
            'lien' => $message->lien,
            'cle_unicite' => $message->cleUnicite,
            'lu' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
