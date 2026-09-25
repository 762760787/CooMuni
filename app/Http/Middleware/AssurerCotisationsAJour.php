<?php

namespace App\Http\Middleware;

use App\Services\CotisationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filet de sécurité si le planificateur (cron) n'est pas configuré : la
 * génération idempotente des cotisations est déclenchée au plus une fois
 * par jour, à la première requête authentifiée.
 */
class AssurerCotisationsAJour
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->isMethod('GET')) {
            $cle = 'coop.cotisations_generees.'.today()->toDateString();
            if (! Cache::has($cle)) {
                Cache::put($cle, true, now()->endOfDay());
                try {
                    app(CotisationService::class)->genererJusqua();
                } catch (\Throwable $e) {
                    Cache::forget($cle);
                    report($e);
                }
            }
        }

        return $next($request);
    }
}
