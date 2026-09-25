<?php

namespace App\Services\Notifications;

final class Message
{
    public function __construct(
        public readonly string $type,
        public readonly string $titre,
        public readonly string $contenu,
        public readonly ?string $lien = null,
        public readonly ?string $cleUnicite = null,
    ) {}
}
