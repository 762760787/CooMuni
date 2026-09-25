{{-- Messages de confirmation / d'erreur (§11.5) --}}
<div x-data class="pointer-events-none fixed inset-x-0 top-3 z-[70] flex flex-col items-center gap-2 px-3 sm:top-auto sm:right-4 sm:bottom-4 sm:left-auto sm:items-end"
     aria-live="polite" role="status">
    <template x-for="t in $store.toasts.liste" :key="t.id">
        <div x-transition.opacity.duration.200ms
             class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl px-4 py-3 text-sm font-medium shadow-lg ring-1"
             :class="t.type === 'erreur' ? 'bg-red-50 text-red-800 ring-red-200' : (t.type === 'info' ? 'bg-sky-50 text-sky-900 ring-sky-200' : 'bg-primaire-800 text-white ring-primaire-900')">
            <span class="mt-0.5 font-bold" x-text="t.type === 'erreur' ? '!' : '✓'"></span>
            <p class="flex-1" x-text="t.message"></p>
            <button type="button" class="-m-1 p-1 opacity-70 hover:opacity-100" @click="$store.toasts.retirer(t.id)" aria-label="Fermer">✕</button>
        </div>
    </template>
</div>

@foreach (['succes' => 'succes', 'erreur' => 'erreur', 'info' => 'info'] as $cle => $type)
    @if (session($cle))
        <div x-data x-init="$nextTick(() => $store.toasts.ajouter(@js(session($cle)), '{{ $type }}'))"></div>
    @endif
@endforeach
