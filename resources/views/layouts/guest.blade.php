<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    @include('partials.head')
</head>
<body class="min-h-full bg-gradient-to-b from-primaire-800 via-primaire-700 to-fond bg-no-repeat [background-size:100%_24rem]"
      data-purger-cache="{{ session('purger_cache') ? '1' : '0' }}">
    <div x-data x-show="!$store.reseau.enLigne" x-cloak class="bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-amber-950">
        Hors connexion — la connexion nécessite un accès réseau.
    </div>
    <main class="mx-auto flex min-h-full w-full max-w-md flex-col px-4 pt-10 pb-8 sm:pt-16">
        <div class="mb-6 flex flex-col items-center text-center text-white">
            <img src="{{ route('logo') }}" alt="Logo de la Mairie de Ngoundiane" class="mb-3 size-24 rounded-2xl bg-white p-1.5 shadow-lg object-contain">
            <h1 class="text-xl font-bold leading-tight">{{ parametre('coop_nom', config('app.name')) }}</h1>
            <p class="mt-1 text-sm text-primaire-100">Gestion des cotisations et de la caisse</p>
        </div>
        {{ $slot }}
        <p class="mt-8 text-center text-xs text-slate-500">© {{ date('Y') }} Commune de Ngoundiane — accès réservé aux membres et responsables de la coopérative.</p>
    </main>
    @include('partials.toasts')
</body>
</html>
