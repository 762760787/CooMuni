<?php

namespace App\Providers;

use App\Http\Middleware\EnsureCompteActif;
use App\Http\Middleware\ExigerChangementMotDePasse;
use App\Services\Parametres;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Parametres::class);
    }

    public function boot(): void
    {
        Carbon::setLocale('fr');
        CarbonImmutable::setLocale('fr');
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        // Politique de mot de passe (§13).
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // Anti brute-force supplémentaire au niveau IP (en plus du limiteur par identifiant).
        RateLimiter::for('connexion', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        // Contrôles appliqués aussi à chaque requête Livewire (actions des composants).
        Livewire::addPersistentMiddleware([EnsureCompteActif::class, ExigerChangementMotDePasse::class]);
    }
}
