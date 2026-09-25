<div class="relative" x-data="{ ouvert: false }" @click.outside="ouvert = false">
    <label for="{{ $mobile ? 'recherche-mobile' : 'recherche-bureau' }}" class="sr-only">Rechercher un membre</label>
    <div class="relative">
        <x-icone nom="recherche" class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-slate-400" />
        <input id="{{ $mobile ? 'recherche-mobile' : 'recherche-bureau' }}" type="search" wire:model.live.debounce.300ms="terme"
               @focus="ouvert = true" @input="ouvert = true" autocomplete="off"
               placeholder="Nom, prénom, matricule ou téléphone…" class="champ pl-10">
    </div>
    @if (mb_strlen(trim($terme)) >= 2)
        <div x-show="ouvert" class="{{ $mobile ? 'mt-3' : 'absolute inset-x-0 top-full z-50 mt-2 rounded-2xl border border-slate-200 bg-white shadow-lg' }} overflow-hidden">
            @forelse ($resultats as $m)
                <a href="{{ route('membres.show', $m) }}" class="flex min-h-14 items-center gap-3 px-4 py-2 hover:bg-primaire-50">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primaire-100 text-xs font-bold text-primaire-800">{{ $m->initiales() }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-slate-800">{{ $m->nom_complet }}</span>
                        <span class="block truncate text-xs text-slate-500">{{ $m->matricule }}{{ $m->telephone ? ' · '.$m->telephone : '' }}{{ $m->service ? ' · '.$m->service : '' }}</span>
                    </span>
                    <x-badge-membre :statut="$m->statut" />
                </a>
            @empty
                <p class="px-4 py-6 text-center text-sm text-slate-500">Aucun membre trouvé pour « {{ $terme }} ».</p>
            @endforelse
        </div>
    @endif
</div>
