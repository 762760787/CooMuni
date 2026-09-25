<?php

namespace App\Services\Notifications;

use App\Models\User;

/**
 * Canal de notification (§18). La V1 n'active que le canal interne ;
 * l'email, le SMS et WhatsApp s'ajoutent en implémentant cette interface
 * puis en les déclarant dans NotificationService::canaux().
 */
interface Canal
{
    public function nom(): string;

    public function estActif(): bool;

    public function envoyer(User $destinataire, Message $message): void;
}
