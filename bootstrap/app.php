<?php

use App\Http\Middleware\AssurerCotisationsAJour;
use App\Http\Middleware\EnsureCompteActif;
use App\Http\Middleware\EnTetesSecurite;
use App\Http\Middleware\ExigerChangementMotDePasse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [EnTetesSecurite::class]);
        $middleware->alias([
            'compte.actif' => EnsureCompteActif::class,
            'mdp.change' => ExigerChangementMotDePasse::class,
            'cotisations.ajour' => AssurerCotisationsAJour::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
        // Derrière un reverse proxy HTTPS (hébergement), faire confiance aux en-têtes X-Forwarded-*.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '127.0.0.1'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
