<div>
    <x-chargement />
    <x-entete-page titre="Rapports" sous-titre="Choisissez un état et une période, affichez l'aperçu puis exportez-le." />

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Sélection --}}
        <form wire:submit="generer" class="carte h-fit space-y-4 p-4 no-print">
            <x-champ label="Type de rapport" for="type">
                <select id="type" wire:model.live="type" class="champ">
                    @foreach ($types as $cle => $t)<option value="{{ $cle }}">{{ $t['libelle'] }}</option>@endforeach
                </select>
            </x-champ>

            @if (in_array('periode', $besoins, true))
                <x-champ label="Période" for="p-periode" :erreur="$errors->first('params.periode')">
                    <input id="p-periode" type="month" wire:model.live="params.periode" class="champ">
                </x-champ>
            @endif
            @if (in_array('annee', $besoins, true))
                <x-champ label="Année" for="p-annee" :erreur="$errors->first('params.annee')">
                    <input id="p-annee" type="number" inputmode="numeric" min="2000" max="2100" wire:model.live="params.annee" class="champ">
                </x-champ>
            @endif
            @if (in_array('du', $besoins, true))
                <div class="grid grid-cols-2 gap-2">
                    <x-champ label="Du" for="p-du" :erreur="$errors->first('params.du')"><input id="p-du" type="date" wire:model.live="params.du" class="champ"></x-champ>
                    <x-champ label="Au" for="p-au" :erreur="$errors->first('params.au')"><input id="p-au" type="date" wire:model.live="params.au" class="champ"></x-champ>
                </div>
            @endif
            @if (in_array('membre_id', $besoins, true))
                <x-champ label="Membre" for="p-membre" :erreur="$errors->first('params.membre_id')">
                    <input type="search" wire:model.live.debounce.300ms="rechercheMembre" placeholder="Filtrer la liste…" class="champ mb-2" aria-label="Filtrer les membres">
                    <select id="p-membre" wire:model.live="params.membre_id" class="champ">
                        <option value="">— Choisir un membre —</option>
                        @foreach ($membres as $m)<option value="{{ $m->id }}">{{ $m->nom }} {{ $m->prenom }} ({{ $m->matricule }})</option>@endforeach
                    </select>
                </x-champ>
            @endif

            <button type="submit" class="btn-primaire w-full"><x-icone nom="rapport" /> Afficher l'aperçu</button>

            @can('rapports.exporter')
                <div class="border-t border-slate-100 pt-4">
                    <p class="etiquette">Exporter</p>
                    <div class="grid grid-cols-3 gap-2">
                        <a href="{{ route('rapports.export', $exportParams + ['format' => 'pdf']) }}" class="btn-secondaire px-2">PDF</a>
                        <a href="{{ route('rapports.export', $exportParams + ['format' => 'xlsx']) }}" class="btn-secondaire px-2">Excel</a>
                        <a href="{{ route('rapports.export', $exportParams + ['format' => 'csv']) }}" class="btn-secondaire px-2">CSV</a>
                    </div>
                </div>
            @endcan
        </form>

        {{-- Aperçu responsive (§19 : lisible sur téléphone) --}}
        <section class="lg:col-span-2">
            @if (! $rapport)
                <div class="carte"><x-vide message="Sélectionnez un rapport puis touchez « Afficher l'aperçu »." icone="rapport" /></div>
            @else
                <div class="carte overflow-hidden">
                    <div class="border-b border-slate-100 p-4">
                        <p class="text-xs text-slate-500">{{ $rapport['cooperative'] }}</p>
                        <h2 class="text-lg font-bold">{{ $rapport['titre'] }}</h2>
                        <p class="text-sm text-slate-500">{{ $rapport['sous_titre'] }}</p>
                    </div>
                    @if ($rapport['resume'])
                        <dl class="grid grid-cols-2 gap-px bg-slate-100 sm:grid-cols-3">
                            @foreach ($rapport['resume'] as $r)
                                <div class="bg-white p-3"><dt class="text-xs text-slate-500">{{ $r['label'] }}</dt><dd class="font-bold tabular-nums">{{ $r['valeur'] }}</dd></div>
                            @endforeach
                        </dl>
                    @endif

                    @php
                        $cols = collect($rapport['colonnes']);
                        $titre = $cols->firstWhere('mobile', 'titre');
                        $sous = $cols->firstWhere('mobile', 'sous');
                        $droite = $cols->firstWhere('mobile', 'droite');
                        $details = $cols->where('mobile', 'detail');
                        $cellule = function ($col, $ligne) {
                            $v = $ligne[$col['cle']] ?? null;
                            return $v instanceof \App\Enums\EtatCotisation ? $v : \App\Services\Rapports::texte($v, $col['type']);
                        };
                    @endphp

                    @if (empty($rapport['lignes']))
                        <x-vide message="Aucune donnée pour cette période." />
                    @else
                        {{-- Mobile : cartes --}}
                        <ul class="divide-y divide-slate-100 md:hidden">
                            @foreach ($rapport['lignes'] as $ligne)
                                <li class="px-4 py-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="font-semibold">{{ $titre ? $cellule($titre, $ligne) : '' }}</p>
                                            @if ($sous)<p class="text-xs text-slate-500">{{ $cellule($sous, $ligne) }}</p>@endif
                                        </div>
                                        @if ($droite)<p class="shrink-0 font-bold tabular-nums">{{ $cellule($droite, $ligne) }}</p>@endif
                                    </div>
                                    <dl class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                                        @foreach ($details as $d)
                                            @php $v = $cellule($d, $ligne); @endphp
                                            @if ($v !== '')
                                                <div class="flex items-center gap-1"><dt class="text-slate-500">{{ $d['label'] }} :</dt>
                                                    <dd>@if ($v instanceof \App\Enums\EtatCotisation)<x-badge-etat :etat="$v" />@else{{ $v }}@endif</dd></div>
                                            @endif
                                        @endforeach
                                    </dl>
                                </li>
                            @endforeach
                        </ul>
                        {{-- Tablette / ordinateur : tableau complet --}}
                        <div class="hidden overflow-x-auto md:block">
                            <table class="tableau">
                                <thead><tr>@foreach ($cols as $c)<th class="{{ in_array($c['type'], ['montant', 'nombre'], true) ? 'text-right' : '' }}">{{ $c['label'] }}</th>@endforeach</tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($rapport['lignes'] as $ligne)
                                        <tr>
                                            @foreach ($cols as $c)
                                                @php $v = $cellule($c, $ligne); @endphp
                                                <td class="{{ in_array($c['type'], ['montant', 'nombre'], true) ? 'text-right tabular-nums' : '' }}">
                                                    @if ($v instanceof \App\Enums\EtatCotisation)<x-badge-etat :etat="$v" />@else{{ $v }}@endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                                @if ($rapport['totaux'])
                                    <tfoot class="bg-slate-50 font-semibold">
                                        <tr>@foreach ($cols as $c)<td class="px-4 py-3 {{ in_array($c['type'], ['montant', 'nombre'], true) ? 'text-right tabular-nums' : '' }}">{{ isset($rapport['totaux'][$c['cle']]) ? \App\Services\Rapports::texte($rapport['totaux'][$c['cle']], $c['type']) : '' }}</td>@endforeach</tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    @endif
                    <p class="border-t border-slate-100 px-4 py-2 text-xs text-slate-400">Généré le {{ $rapport['genere_le']->format('d/m/Y à H:i') }}</p>
                </div>
            @endif
        </section>
    </div>
</div>
