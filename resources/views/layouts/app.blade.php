@php
    $user = auth()->user();
    $sections = \App\Support\Navigation::sections($user);
    $barre = \App\Support\Navigation::barreMobile($user);
    $nonLues = $user->notificationsInternes()->nonLues()->count();
    $nomCourt = parametre('coop_nom_court', 'Coopérative');
@endphp
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    @include('partials.head')
</head>
<body class="min-h-full" data-purger-cache="{{ session('purger_cache') ? '1' : '0' }}" x-data="{ menu: false, recherche: false }"
      @keydown.escape.window="menu = false; recherche = false">

    {{-- Bandeau hors connexion (§12.2 / §12.4) --}}
    <div x-data x-show="!$store.reseau.enLigne" x-cloak
         class="sticky top-0 z-[60] flex items-center justify-center gap-2 bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-amber-950">
        <x-icone nom="wifi-off" class="size-5 shrink-0" />
        Hors connexion — consultation limitée ; la saisie des paiements et opérations est bloquée.
    </div>

    {{-- Barre latérale (tablette large / ordinateur) --}}
    <aside class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col bg-primaire-900 lg:flex">
        @include('partials.navigation', ['sections' => $sections])
    </aside>

    {{-- Menu complémentaire mobile (hamburger) --}}
    <div x-show="menu" x-cloak class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="Menu">
        <div x-show="menu" x-transition.opacity class="absolute inset-0 bg-slate-900/50" @click="menu = false"></div>
        <aside x-show="menu" x-transition:enter="transition duration-200" x-transition:enter-start="-translate-x-full"
               x-transition:leave="transition duration-150" x-transition:leave-end="-translate-x-full"
               class="relative flex h-full w-72 max-w-[85%] flex-col bg-primaire-900 shadow-xl">
            <button type="button" class="absolute top-3 right-3 btn-icone text-primaire-100 hover:bg-white/10" @click="menu = false" aria-label="Fermer le menu">
                <x-icone nom="fermer" />
            </button>
            @include('partials.navigation', ['sections' => $sections])
        </aside>
    </div>

    <div class="lg:pl-64">
        {{-- En-tête --}}
        <header class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/90 backdrop-blur supports-[backdrop-filter]:bg-white/75">
            <div class="mx-auto flex h-16 max-w-7xl items-center gap-2 px-2 sm:px-4 lg:px-8">
                <button type="button" class="btn-icone lg:hidden" @click="menu = true" aria-label="Ouvrir le menu">
                    <x-icone nom="menu" class="size-6" />
                </button>
                <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2 lg:hidden">
                    <img src="{{ route('logo') }}" alt="" class="size-8 object-contain">
                    <span class="truncate text-base font-bold text-primaire-900">{{ $title ?? $nomCourt }}</span>
                </a>

                @can('membres.voir')
                    {{-- Recherche globale : en ligne sur ordinateur, plein écran sur mobile (§11.2) --}}
                    <div class="hidden flex-1 lg:block lg:max-w-md">
                        <livewire:recherche-globale />
                    </div>
                @endcan

                <div class="ml-auto flex items-center gap-1">
                    @can('membres.voir')
                        <button type="button" class="btn-icone lg:hidden" @click="recherche = true; $nextTick(() => document.getElementById('recherche-mobile')?.focus())" aria-label="Rechercher un membre">
                            <x-icone nom="recherche" class="size-6" />
                        </button>
                    @endcan
                    <a href="{{ route('notifications') }}" class="btn-icone relative" aria-label="Notifications ({{ $nonLues }} non lues)">
                        <x-icone nom="cloche" class="size-6" />
                        @if ($nonLues)
                            <span class="absolute top-1.5 right-1.5 flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[11px] font-bold text-white">{{ $nonLues > 9 ? '9+' : $nonLues }}</span>
                        @endif
                    </a>
                    <div x-data="{ ouvert: false }" class="relative">
                        <button type="button" @click="ouvert = !ouvert" @click.outside="ouvert = false"
                                class="flex min-h-11 items-center gap-2 rounded-xl px-1.5 hover:bg-slate-100" aria-label="Menu du compte">
                            <span class="flex size-9 items-center justify-center rounded-full bg-primaire-700 text-sm font-bold text-white">{{ $user->initiales() }}</span>
                            <span class="hidden text-left leading-tight sm:block">
                                <span class="block max-w-40 truncate text-sm font-semibold text-slate-800">{{ $user->name }}</span>
                                <span class="block text-xs text-slate-500">{{ $user->roleNom() }}</span>
                            </span>
                        </button>
                        <div x-show="ouvert" x-cloak x-transition.origin.top.right
                             class="absolute right-0 mt-2 w-56 overflow-hidden rounded-2xl border border-slate-200 bg-white py-1 shadow-lg">
                            <a href="{{ route('profil') }}" class="flex min-h-11 items-center gap-2 px-4 text-sm hover:bg-slate-50"><x-icone nom="profil" /> Mon profil</a>
                            <template x-if="$store.installation.disponible">
                                <button type="button" @click="$store.installation.installer()" class="flex min-h-11 w-full items-center gap-2 px-4 text-sm hover:bg-slate-50"><x-icone nom="installer" /> Installer l'application</button>
                            </template>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex min-h-11 w-full items-center gap-2 px-4 text-sm text-red-700 hover:bg-red-50"><x-icone nom="deconnexion" /> Se déconnecter</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        @can('membres.voir')
            <div x-show="recherche" x-cloak class="fixed inset-0 z-50 flex flex-col bg-white p-3 lg:hidden" role="dialog" aria-label="Recherche">
                <div class="mb-2 flex items-center gap-2">
                    <button type="button" class="btn-icone" @click="recherche = false" aria-label="Fermer la recherche"><x-icone nom="chevron-gauche" class="size-6" /></button>
                    <span class="font-semibold">Rechercher un membre</span>
                </div>
                <livewire:recherche-globale :mobile="true" />
            </div>
        @endcan

        <main class="mx-auto max-w-7xl px-4 pt-4 pb-32 sm:px-6 lg:px-8 lg:pt-6 lg:pb-12">
            {{ $slot }}
        </main>
    </div>

    {{-- Bouton d'action flottant : enregistrer un paiement (§11.2) --}}
    @can('paiements.creer')
        @unless (request()->routeIs('paiements.create'))
            <a href="{{ route('paiements.create') }}"
               class="fixed right-4 bottom-24 z-30 flex h-14 items-center gap-2 rounded-2xl bg-or-400 px-5 font-bold text-primaire-950 shadow-lg shadow-primaire-900/20 ring-1 ring-or-500 hover:bg-or-500 lg:bottom-8"
               aria-label="Enregistrer un paiement">
                <x-icone nom="plus" class="size-6" /> <span>Paiement</span>
            </a>
        @endunless
    @endcan

    {{-- Barre de navigation inférieure (mobile) --}}
    <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 backdrop-blur lg:hidden" style="padding-bottom: env(safe-area-inset-bottom)" aria-label="Navigation principale">
        <div class="mx-auto grid max-w-lg grid-cols-5">
            @foreach ($barre as $item)
                @php $actif = request()->routeIs($item['actif']); @endphp
                <a href="{{ route($item['route']) }}" class="flex min-h-16 flex-col items-center justify-center gap-0.5 text-[11px] font-medium {{ $actif ? 'text-primaire-700' : 'text-slate-500' }}" @if ($actif) aria-current="page" @endif>
                    <span class="flex h-7 w-12 items-center justify-center rounded-full {{ $actif ? 'bg-primaire-100' : '' }}"><x-icone :nom="$item['icone']" class="size-6" /></span>
                    {{ $item['label'] }}
                </a>
            @endforeach
            @for ($i = count($barre); $i < 4; $i++)
                <span></span>
            @endfor
            <button type="button" @click="menu = true" class="flex min-h-16 flex-col items-center justify-center gap-0.5 text-[11px] font-medium text-slate-500">
                <span class="flex h-7 w-12 items-center justify-center"><x-icone nom="menu" class="size-6" /></span>
                Plus
            </button>
        </div>
    </nav>

    @include('partials.toasts')
</body>
</html>
