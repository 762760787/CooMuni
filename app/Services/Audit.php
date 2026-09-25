<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Journal d'audit des actions sensibles (§8.1 Traçabilité, §13, §14).
 */
class Audit
{
    /** Champs jamais écrits dans le journal. */
    private const MASQUES = ['password', 'remember_token'];

    public static function log(
        string $action,
        Model|string|null $cible = null,
        ?array $avant = null,
        ?array $apres = null,
        ?string $description = null,
        ?int $userId = null,
    ): AuditLog {
        [$type, $id] = match (true) {
            $cible instanceof Model => [class_basename($cible), $cible->getKey()],
            is_string($cible) => [$cible, null],
            default => [null, null],
        };

        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'cible_type' => $type,
            'cible_id' => $id,
            'description' => $description ? Str::limit($description, 250) : null,
            'ancienne_valeur' => self::nettoyer($avant),
            'nouvelle_valeur' => self::nettoyer($apres),
            'ip' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : 'console',
            'date_action' => now(),
        ]);
    }

    /** Différentiel avant/après limité aux champs modifiés. */
    public static function diff(array $avant, array $apres): array
    {
        $a = [];
        $b = [];
        foreach ($apres as $cle => $valeur) {
            $ancien = $avant[$cle] ?? null;
            if (self::normaliser($ancien) !== self::normaliser($valeur)) {
                $a[$cle] = $ancien;
                $b[$cle] = $valeur;
            }
        }

        return [$a, $b];
    }

    private static function normaliser(mixed $v): ?string
    {
        if ($v instanceof \BackedEnum) {
            return (string) $v->value;
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }

        return $v === null || $v === '' ? null : (string) $v;
    }

    private static function nettoyer(?array $valeurs): ?array
    {
        if ($valeurs === null) {
            return null;
        }
        foreach ($valeurs as $cle => $v) {
            if (in_array($cle, self::MASQUES, true)) {
                $valeurs[$cle] = '••••••';
            } elseif ($v instanceof \BackedEnum) {
                $valeurs[$cle] = $v->value;
            } elseif ($v instanceof \DateTimeInterface) {
                $valeurs[$cle] = $v->format('Y-m-d');
            }
        }

        return $valeurs;
    }
}
