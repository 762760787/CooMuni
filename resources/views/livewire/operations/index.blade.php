<div>
    <x-chargement />
    <x-entete-page titre="Opérations financières" sous-titre="Mouvements de caisse hors cotisations (les cotisations sont suivies dans Paiements).">
        <x-slot:actions>
            @can('operations.creer')<a href="{{ route('operations.create') }}" class="btn-primaire"><x-icone nom="plus" /> Nouvelle opération</a>@endcan
        </x-slot:actions>
    </x-entete-page>

    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-3">
        <x-stat label="Entrées (filtre)" :valeur="fcfa($entrees)" icone="fleche-entree" />
        <x-stat label="Sorties (filtre)" :valeur="fcfa($sorties)" icone="fleche-sortie" ton="rouge" />
        <x-stat label="Solde de caisse actuel" :valeur="fcfa($solde)" sous="Solde initial + cotisations + entrées − sorties" icone="caisse" ton="or" class="col-span-2 lg:col-span-1" />
    </div>

    <div class="carte mb-4 grid gap-2 p-3 sm:grid-cols-3 lg:grid-cols-6">
        <input type="search" wire:model.live.debounce.300ms="recherche" placeholder="Description, n°, référence…" class="champ sm:col-span-3 lg:col-span-2" aria-label="Rechercher">
        <select wire:model.live="type" class="champ" aria-label="Type">
            <option value="">Entrées et sorties</option><option value="entree">Entrées</option><option value="sortie">Sorties</option>
        </select>
        <select wire:model.live="categorie" class="champ" aria-label="Catégorie">
            <option value="">Toutes catégories</option>
            @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->type === 'entree' ? '↘' : '↗' }} {{ $c->nom }}</option>@endforeach
        </select>
        <select wire:model.live="statut" class="champ" aria-label="Statut">
            <option value="">Tous statuts</option><option value="valide">Validées</option><option value="demande">Annulation demandée</option><option value="annule">Annulées</option>
        </select>
        <div class="grid grid-cols-2 gap-2 sm:col-span-3 lg:col-span-1">
            <input type="date" wire:model.live="du" class="champ" aria-label="Du">
            <input type="date" wire:model.live="au" class="champ" aria-label="Au">
        </div>
    </div>

    <div class="carte overflow-hidden">
        @if ($operations->isEmpty())
            <x-vide message="Aucune opération." icone="caisse" />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($operations as $o)
                    <li wire:key="o-{{ $o->id }}">
                        <a href="{{ route('operations.show', $o) }}" class="flex min-h-16 items-center gap-3 px-4 py-3 hover:bg-slate-50">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $o->estEntree() ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                <x-icone :nom="$o->estEntree() ? 'fleche-entree' : 'fleche-sortie'" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold {{ $o->estAnnule() ? 'text-slate-400 line-through' : '' }}">{{ $o->categorie->nom }}</span>
                                <span class="block truncate text-xs text-slate-500">{{ $o->description }}</span>
                                <span class="block text-xs text-slate-500">{{ $o->date_operation->format('d/m/Y') }} · {{ $o->numero }}{{ $o->justificatif ? ' · 📎' : '' }}</span>
                            </span>
                            <span class="flex flex-col items-end gap-1">
                                <span class="font-bold tabular-nums {{ $o->estAnnule() ? 'text-slate-400 line-through' : ($o->estEntree() ? 'text-emerald-700' : 'text-red-700') }}">{{ $o->estEntree() ? '+' : '−' }}{{ fcfa($o->montant) }}</span>
                                @if ($o->estAnnule())<span class="badge bg-slate-100 text-slate-600 ring-slate-400/20">Annulée</span>
                                @elseif ($o->annulationEnAttente())<span class="badge bg-amber-100 text-amber-900 ring-amber-500/30">Annulation demandée</span>@endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
            {{ $operations->links() }}
        @endif
    </div>
</div>
