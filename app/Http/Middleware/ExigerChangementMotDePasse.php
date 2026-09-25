<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Mot de passe temporaire : changement obligatoire avant tout accès. */
class ExigerChangementMotDePasse
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->doit_changer_mdp && ! $request->routeIs('password.change', 'logout')) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
