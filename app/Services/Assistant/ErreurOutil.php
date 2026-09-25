<?php

namespace App\Services\Assistant;

use RuntimeException;

/** Erreur renvoyée à l'IA (tool_result is_error) pour qu'elle corrige ou questionne l'utilisateur. */
class ErreurOutil extends RuntimeException {}
