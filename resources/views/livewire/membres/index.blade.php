<div>
    <x-chargement />
    <x-entete-page titre="Membres" :sous-titre="$membres->total().' membre(s)'">
        <x-slot:actions>
            @can('membres.creer')
                <a href="{{ route('membres.create') }}" class="btn-primaire"><x-icone nom="plus" /> Nouveau membre</a>
            @endcan
        </x-slot:actions>
    </x-entete-page>

    {{-- Recherche et filtres (§7.11) --}}
    <div class="carte mb-4 p-3" x-data="{ filtres: {{ ($statut || $service) ? 'true' : 'false' }} }">
        <div class="flex gap-2">
            <div class="relative flex-1">
                <x-icone nom="recherche" class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-slate-400" />
                <input type="search" wire:model.live.debounce.300ms="recherche" placeholder="Nom, prénom, matricule, téléphone…" class="champ pl-10" aria-label="Rechercher">
            </div>
            <button type="button" @click="filtres = !filtres" class="btn-secondaire px-3 sm:hidden" :aria-expanded="filtres" aria-label="Filtres">
                <x-icone nom="filtre" />
            </button>
        </div>
        <div class="mt-2 grid gap-2 sm:!grid sm:grid-cols-4" :class="filtres ? 'grid' : 'hidden'">
            <select wire:model.live="statut" class="champ" aria-label="Statut">
                <option value="">Tous les statuts</option>
                @foreach ($statuts as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
            </select>
            <select wire:model.live="service" class="champ" aria-label="Service">
                <option value="">Tous les services</option>
                @foreach ($services as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
            </select>
            <select wire:model.live="tri" class="champ" aria-label="Trier par">
                <option value="nom">Tri : nom</option>
                <option value="matricule">Tri : matricule</option>
                <option value="adhesion">Tri : adhésion récente</option>
            </select>
            <button type="button" wire:click="reinitialiser" class="btn-secondaire">Réinitialiser</button>
        </div>
    </div>

    <div class="carte overflow-hidden">
        @if ($membres->isEmpty())
            <x-vide message="Aucun membre ne correspond à ces critères." icone="membres" />
        @else
            {{-- Mobile : cartes --}}
            <ul class="divide-y divide-slate-100 md:hidden">
                @foreach ($membres as $m)
                    @php $c = $m->cotisations->first(); @endphp
                    <li wire:key="m-{{ $m->id }}">
                        <a href="{{ route('membres.show', $m) }}" class="flex min-h-16 items-center gap-3 px-4 py-3 active:bg-slate-50">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primaire-100 text-sm font-bold text-primaire-800">{{ $m->initiales() }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold text-slate-900">{{ $m->nom_complet }}</span>
                                <span class="block truncate text-xs text-slate-500">{{ $m->matricule }}{{ $m->service ? ' · '.$m->service : '' }}</span>
                            </span>
                            <span class="flex flex-col items-end gap-1">
                                @if ($m->statut !== \App\Enums\StatutMembre::Actif)<x-badge-membre :statut="$m->statut" />@endif
                                @if ($c)<x-badge-etat :etat="$c->etat()" />@endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>

            {{-- Tablette / ordinateur : tableau --}}
            <div class="hidden overflow-x-auto md:block">
                <table class="tableau">
                    <thead>
                        <tr>
                            <th>Matricule</th><th>Membre</th><th>Téléphone</th><th class="hidden lg:table-cell">Service</th>
                            <th class="hidden lg:table-cell">Adhésion</th><th>Statut</th><th>{{ $periodeCourante->libelle() }}</th><th class="sr-only">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($membres as $m)
                            @php $c = $m->cotisations->first(); @endphp
                            <tr wire:key="t-{{ $m->id }}">
                                <td class="font-mono text-xs text-slate-600">{{ $m->matricule }}</td>
                                <td><a href="{{ route('membres.show', $m) }}" class="font-semibold text-slate-900 hover:text-primaire-700">{{ $m->nom_complet }}</a></td>
                                <td class="text-slate-600">{{ $m->telephone ?? '—' }}</td>
                                <td class="hidden text-slate-600 lg:table-cell">{{ $m->service ?? '—' }}</td>
                                <td class="hidden text-slate-600 lg:table-cell">{{ $m->date_adhesion->format('d/m/Y') }}</td>
                                <td><x-badge-membre :statut="$m->statut" /></td>
                                <td>@if ($c)<x-badge-etat :etat="$c->etat()" />@else<span class="text-xs text-slate-400">—</span>@endif</td>
                                <td class="text-right"><a href="{{ route('membres.show', $m) }}" class="btn-lien text-sm">Fiche</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $membres->links() }}
        @endif
    </div>
</div>
