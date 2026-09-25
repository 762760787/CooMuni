<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Violation d'une règle de gestion : le message est destiné à l'utilisateur
 * (en français, sans détail technique).
 */
class RegleMetierException extends RuntimeException {}
