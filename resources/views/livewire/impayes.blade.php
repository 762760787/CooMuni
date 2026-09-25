<div>
    <x-chargement />
    <x-entete-page titre="Impayés" sous-titre="Cotisations non réglées (ou partiellement) dont l'échéance est dépassée.">
        <x-slot:actions>
            @can('rapports.exporter')
                <a href="{{ route('rapports.export', ['type' => 'membres_impayes', 'format' => 'pdf', 'periode' => $periode ?: (string) \App\Support\Periode::courante()]) }}" class="btn-secondaire"><x-icone nom="telecharger" /> PDF</a>
                <a href="{{ route('rapports.export', ['type' => 'membres_impayes', 'format' => 'xlsx', 'periode' => $periode ?: (string) \App\Support\Periode::courante()]) }}" class="btn-secondaire">Excel</a>
            @endcan
        </x-slot:actions>
    </x-entete-page>

    <div class="mb-4 grid grid-cols-3 gap-3">
        <x-stat label="Membres" :valeur="(int) $totaux->m" icone="membres" ton="rouge" />
        <x-stat label="Mois impayés" :valeur="(int) $totaux->n" icone="calendrier" ton="ambre" />
        <x-stat label="Montant dû" :valeur="fcfa($totaux->t)" icone="billet" ton="rouge" />
    </div>

    <div class="carte mb-4 grid gap-2 p-3 sm:grid-cols-4">
        <input type="search" wire:model.live.debounce.300ms="recherche" placeholder="Rechercher un membre…" class="champ" aria-label="Rechercher">
        <select wire:model.live="service" class="champ" aria-label="Service">
            <option value="">Tous les services</option>
            @foreach ($services as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
        </select>
        <label class="flex items-center gap-2"><span class="shrink-0 text-sm text-slate-600">Jusqu'à</span><input type="month" wire:model.live="periode" class="champ" aria-label="Jusqu'à la période"></label>
        <select wire:model.live="minMois" class="champ" aria-label="Nombre de mois minimum">
            <option value="0">Tous (≥ 1 mois)</option>
            <option value="2">≥ 2 mois impayés</option>
            <option value="3">≥ 3 mois impayés</option>
        </select>
    </div>

    <div class="carte overflow-hidden">
        @if ($groupes->isEmpty())
            <x-vide message="Aucun impayé : tous les membres sont à jour." icone="check" />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($groupes as $g)
                    @php $m = $membres[$g->membre_id]; @endphp
                    <li wire:key="i-{{ $g->membre_id }}" class="flex flex-wrap items-center gap-3 px-4 py-3 sm:flex-nowrap">
                        <a href="{{ route('membres.historique', $m) }}" class="flex min-w-0 flex-1 items-center gap-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-sm font-bold text-red-700">{{ $g->nb_mois }}</span>
                            <span class="min-w-0">
                                <span class="block truncate font-semibold">{{ $m->nom_complet }}</span>
                                <span class="block truncate text-xs text-slate-500">
                                    {{ $m->matricule }} ·
                                    {{ $g->premiere === $g->derniere ? periode_libelle($g->premiere) : periode_libelle($g->premiere).' → '.periode_libelle($g->derniere) }}
                                </span>
                            </span>
                        </a>
                        <div class="flex w-full items-center justify-between gap-2 pl-13 sm:w-auto sm:pl-0">
                            <strong class="text-red-700 tabular-nums">{{ fcfa($g->montant_du) }}</strong>
                            <div class="flex gap-1">
                                @if ($m->telephone)<a href="tel:{{ $m->telephone }}" class="btn-icone size-10" aria-label="Appeler {{ $m->nom_complet }}">📞</a>@endif
                                @canany(['paiements.creer', 'cotisations.corriger'])
                                    <button type="button" wire:click="relancer({{ $m->id }})" class="btn-icone size-10" @disabled(! $m->user) title="{{ $m->user ? 'Envoyer un rappel (notification interne)' : 'Pas de compte : relance SMS/WhatsApp prévue en V2' }}" aria-label="Relancer"><x-icone nom="cloche" /></button>
                                @endcanany
                                @can('paiements.creer')
                                    <a href="{{ route('paiements.create', ['membre' => $m->id]) }}" class="btn-primaire min-h-10 px-3 text-xs">Encaisser</a>
                                @endcan
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
            {{ $groupes->links() }}
        @endif
    </div>
    <p class="mt-3 text-xs text-slate-500">La relance par SMS ou WhatsApp nécessite une intégration externe (prévue en V2/V3). En V1, le rappel est une notification interne pour les membres disposant d'un compte.</p>
</div>
