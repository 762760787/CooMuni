@props(['nom', 'titre', 'taille' => 'max-w-lg'])
{{--
    Fenêtre modale pilotée par évènements navigateur :
    ouverture : $dispatch('ouvrir-modal', '{{ nom }}') ; fermeture depuis PHP : $this->dispatch('fermer-modal')
    Sur mobile elle s'affiche en « feuille » depuis le bas de l'écran.
--}}
<div x-data="{ ouvert: false }"
     x-on:ouvrir-modal.window="if ($event.detail === '{{ $nom }}' || $event.detail?.[0] === '{{ $nom }}') ouvert = true"
     x-on:fermer-modal.window="ouvert = false"
     x-on:keydown.escape.window="ouvert = false"
     x-show="ouvert" x-cloak class="fixed inset-0 z-[65] flex items-end justify-center sm:items-center sm:p-4"
     role="dialog" aria-modal="true" aria-label="{{ $titre }}">
    <div x-show="ouvert" x-transition.opacity class="absolute inset-0 bg-slate-900/50" @click="ouvert = false"></div>
    <div x-show="ouvert" x-transition:enter="transition duration-200" x-transition:enter-start="translate-y-8 opacity-0"
         class="relative max-h-[92vh] w-full {{ $taille }} overflow-y-auto rounded-t-3xl bg-white p-5 shadow-xl sm:rounded-3xl"
         style="padding-bottom: max(1.25rem, env(safe-area-inset-bottom))">
        <div class="mb-4 flex items-start justify-between gap-3">
            <h2 class="text-lg font-bold text-slate-900">{{ $titre }}</h2>
            <button type="button" class="btn-icone -mt-2 -mr-2" @click="ouvert = false" aria-label="Fermer"><x-icone nom="fermer" /></button>
        </div>
        {{ $slot }}
    </div>
</div>
