<div>
    <x-chargement />
    @php $user = auth()->user(); @endphp

    <x-entete-page :titre="'Bonjour, '.\Illuminate\Support\Str::of($user->name)->before(' (')" :sous-titre="now()->translatedFormat('l j F Y')">
        @if ($global)
            <x-slot:actions>
                <div class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white p-1">
                    <button type="button" wire:click="changerPeriode(-1)" class="btn-icone size-9" aria-label="Mois précédent"><x-icone nom="chevron-gauche" /></button>
                    <span class="min-w-32 text-center text-sm font-semibold">{{ $periodeObj->libelle() }}</span>
                    <button type="button" wire:click="changerPeriode(1)" class="btn-icone size-9" aria-label="Mois suivant"><x-icone nom="chevron-droite" /></button>
                </div>
            </x-slot:actions>
        @endif
    </x-entete-page>

    {{-- ===== Espace personnel du membre ===== --}}
    @if ($perso)
        @php $c = $perso['courante']; $s = $perso['stats']; @endphp
        <section class="mb-6" aria-labelledby="ma-situation">
            <div class="carte overflow-hidden">
                <div class="bg-gradient-to-br from-primaire-700 to-primaire-900 p-5 text-white">
                    <h2 id="ma-situation" class="text-sm font-medium text-primaire-100">Ma cotisation de {{ \App\Support\Periode::courante()->libelle() }}</h2>
                    @if ($c)
                        <div class="mt-2 flex flex-wrap items-center gap-3">
                            <p class="text-3xl font-bold tabular-nums">{{ fcfa($c->reste ?: $c->montant_paye) }}</p>
                            <x-badge-etat :etat="$c->etat()" class="!ring-white/30" />
                        </div>
                        <p class="mt-1 text-sm text-primaire-100">
                            @if ($c->statut->estDue())
                                Reste à payer — échéance le {{ $c->date_echeance->format('d/m/Y') }}
                            @else
                                Réglée le {{ date_fr($c->date_paiement) }}
                            @endif
                        </p>
                    @else
                        <p class="mt-2 text-sm text-primaire-100">Aucune cotisation due pour ce mois.</p>
                    @endif
                </div>
                <div class="grid grid-cols-2 divide-x divide-y divide-slate-100 sm:grid-cols-4 sm:divide-y-0">
                    <div class="p-4"><p class="text-xs text-slate-500">Solde dû (échu)</p><p class="text-lg font-bold tabular-nums {{ $s['solde_du'] ? 'text-red-700' : 'text-slate-900' }}">{{ fcfa($s['solde_du']) }}</p></div>
                    <div class="p-4"><p class="text-xs text-slate-500">Total versé</p><p class="text-lg font-bold tabular-nums">{{ fcfa($s['total_verse']) }}</p></div>
                    <div class="p-4"><p class="text-xs text-slate-500">Mois payés</p><p class="text-lg font-bold">{{ $s['mois_payes'] }}</p></div>
                    <div class="p-4"><p class="text-xs text-slate-500">Retards / impayés</p><p class="text-lg font-bold">{{ $s['retards'] }} / {{ $s['impayes'] }}</p></div>
                </div>
                <div class="border-t border-slate-100 p-4">
                    <p class="mb-2 text-xs font-semibold text-slate-500 uppercase">Année {{ \App\Support\Periode::courante()->annee }}</p>
                    <div class="grid grid-cols-6 gap-2 sm:grid-cols-12">
                        @foreach (range(1, 12) as $m)
                            @php $cm = $perso['annee'][$m] ?? null; @endphp
                            <div class="flex flex-col items-center gap-1" title="{{ \App\Support\Periode::nomMois($m) }} : {{ $cm ? $cm->etat()->label() : 'non concerné' }}">
                                <span class="flex size-8 items-center justify-center rounded-full text-xs font-bold text-white {{ $cm ? $cm->etat()->pastille() : 'bg-slate-100 !text-slate-400' }}">{{ $cm ? $cm->etat()->icone() : '·' }}</span>
                                <span class="text-[11px] text-slate-500">{{ \App\Support\Periode::abreviation($m) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 border-t border-slate-100 p-4">
                    <a href="{{ route('mon-historique') }}" class="btn-primaire flex-1">Mon historique</a>
                    <a href="{{ route('recus.index') }}" class="btn-secondaire flex-1">Mes reçus</a>
                </div>
            </div>
        </section>
    @endif

    {{-- ===== Indicateurs globaux ===== --}}
    @if ($global)
        @php $st = $global['stats']; @endphp

        {{-- Alertes --}}
        <div class="mb-4 space-y-2">
            @if ($global['demandes'] > 0)
                <a href="{{ route('paiements.index', ['statut' => 'demande']) }}" class="flex min-h-11 items-center gap-3 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <x-icone nom="alerte" class="size-5 shrink-0" />
                    <span class="flex-1"><strong>{{ $global['demandes'] }}</strong> demande(s) d'annulation en attente de validation.</span>
                    <x-icone nom="chevron-droite" />
                </a>
            @endif
            @if ($periodeObj->compare(\App\Support\Periode::courante()) === 0 && today()->lte($st['echeance']))
                <div class="flex items-center gap-3 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                    <x-icone nom="horloge" class="size-5 shrink-0" />
                    Échéance le {{ $st['echeance']->translatedFormat('j F') }}
                    ({{ today()->isSameDay($st['echeance']) ? 'aujourd\'hui' : 'dans '.today()->diffInDays($st['echeance']).' jour(s)' }}) —
                    {{ $st['a_venir'] }} membre(s) n'ont pas encore payé.
                </div>
            @endif
        </div>

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Indicateurs clés">
            <x-stat label="Membres actifs" :valeur="$global['effectifs']['actif']" :sous="$global['effectifs']['total'].' inscrits au total'" icone="membres" :lien="$user->can('membres.voir') ? route('membres.index') : null" />
            <x-stat label="Attendu du mois" :valeur="fcfa($st['attendu'])" :sous="$st['total'].' cotisations'" icone="calendrier" ton="gris" />
            <x-stat label="Encaissé" :valeur="fcfa($st['encaisse'])" :sous="'Taux : '.str_replace('.', ',', $st['taux']).' %'" icone="billet" ton="or" />
            <x-stat label="Reste à recouvrer" :valeur="fcfa($st['reste'])" :sous="$st['impayes'].' impayé(s)'" icone="alerte" ton="rouge" :lien="$user->can('impayes.voir') ? route('impayes') : null" />
        </section>

        {{-- Taux de recouvrement et répartition --}}
        <section class="mt-3 grid gap-3 lg:grid-cols-3">
            <div class="carte p-4 lg:col-span-2">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-semibold text-slate-900">Situation de {{ $periodeObj->libelle() }}</h2>
                    @can('cotisations.voir')<a href="{{ route('cotisations.index', ['periode' => (string) $periodeObj]) }}" class="btn-lien text-sm">Détail</a>@endcan
                </div>
                <div class="mb-1 flex justify-between text-sm"><span class="text-slate-600">Taux de recouvrement</span><strong class="tabular-nums">{{ str_replace('.', ',', $st['taux']) }} %</strong></div>
                <div class="h-3 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow="{{ $st['taux'] }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="h-full rounded-full bg-primaire-600" style="width: {{ min(100, $st['taux']) }}%"></div>
                </div>
                @php $total = max(1, $st['total'] - $st['annules']); @endphp
                <div class="mt-4 flex h-3 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                    <div class="bg-emerald-600" style="width: {{ $st['a_temps'] * 100 / $total }}%"></div>
                    <div class="bg-amber-500" style="width: {{ $st['en_retard'] * 100 / $total }}%"></div>
                    <div class="bg-teal-500" style="width: {{ $st['regularises'] * 100 / $total }}%"></div>
                    <div class="bg-violet-500" style="width: {{ ($st['partiels']) * 100 / $total }}%"></div>
                    <div class="bg-red-600" style="width: {{ max(0, $st['impayes'] - $st['partiels']) * 100 / $total }}%"></div>
                </div>
                <dl class="mt-3 grid grid-cols-2 gap-2 text-sm sm:grid-cols-5">
                    <div><dt class="flex items-center gap-1.5 text-slate-500"><span class="size-2.5 rounded-sm bg-emerald-600"></span>À temps</dt><dd class="font-bold">{{ $st['a_temps'] }}</dd></div>
                    <div><dt class="flex items-center gap-1.5 text-slate-500"><span class="size-2.5 rounded-sm bg-amber-500"></span>En retard</dt><dd class="font-bold">{{ $st['en_retard'] }}</dd></div>
                    <div><dt class="flex items-center gap-1.5 text-slate-500"><span class="size-2.5 rounded-sm bg-violet-500"></span>Partiels</dt><dd class="font-bold">{{ $st['partiels'] }}</dd></div>
                    <div><dt class="flex items-center gap-1.5 text-slate-500"><span class="size-2.5 rounded-sm bg-red-600"></span>Impayés</dt><dd class="font-bold">{{ $st['impayes'] }}</dd></div>
                    <div><dt class="flex items-center gap-1.5 text-slate-500"><span class="size-2.5 rounded-sm bg-sky-500"></span>À venir</dt><dd class="font-bold">{{ $st['a_venir'] }}</dd></div>
                </dl>
            </div>
            <div class="grid gap-3">
                <x-stat label="Solde de caisse" :valeur="fcfa($global['solde'])" sous="Cotisations + entrées − sorties" icone="caisse" :lien="$user->can('operations.voir') ? route('operations.index') : null" />
                <x-stat label="Impayés cumulés" :valeur="fcfa($global['impayes']['montant'])" :sous="$global['impayes']['nombre'].' mois · '.$global['impayes']['membres'].' membre(s)'" icone="alerte" ton="rouge" :lien="$user->can('impayes.voir') ? route('impayes') : null" />
            </div>
        </section>

        {{-- Graphiques --}}
        <section class="mt-3 grid gap-3 lg:grid-cols-2" aria-label="Graphiques">
            <div class="carte p-4">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="font-semibold text-slate-900">Cotisations attendues / encaissées</h2>
                    <div class="flex rounded-lg bg-slate-100 p-0.5 text-xs">
                        @foreach ([6, 12] as $n)
                            <button type="button" wire:click="$set('nbMois', {{ $n }})" class="min-h-9 rounded-md px-2.5 font-semibold {{ $nbMois === $n ? 'bg-white shadow-sm' : 'text-slate-500' }}">{{ $n }} mois</button>
                        @endforeach
                    </div>
                </div>
                <x-graphique-barres :donnees="collect($global['serie'])->map(fn ($s) => ['libelle' => $s['libelle'], 'valeurs' => ['attendu' => $s['attendu'], 'encaisse' => $s['encaisse']]])->all()"
                    :series="[['cle' => 'attendu', 'nom' => 'Attendu', 'couleur' => 'bg-slate-300'], ['cle' => 'encaisse', 'nom' => 'Encaissé', 'couleur' => 'bg-primaire-600']]" />
            </div>
            <div class="carte p-4">
                <h2 class="mb-3 font-semibold text-slate-900">Encaissements mensuels (trésorerie)</h2>
                <x-graphique-barres :donnees="collect($global['encaissements'])->map(fn ($s) => ['libelle' => $s['libelle'], 'valeurs' => ['m' => $s['montant']]])->all()"
                    :series="[['cle' => 'm', 'nom' => 'Paiements reçus dans le mois', 'couleur' => 'bg-or-400']]" />
            </div>
            <div class="carte p-4">
                <h2 class="mb-3 font-semibold text-slate-900">Impayés et retards par mois</h2>
                <x-graphique-barres format="nombre" :donnees="collect($global['serie'])->map(fn ($s) => ['libelle' => $s['libelle'], 'valeurs' => ['i' => $s['impayes'], 'r' => $s['retards']]])->all()"
                    :series="[['cle' => 'i', 'nom' => 'Impayés', 'couleur' => 'bg-red-500'], ['cle' => 'r', 'nom' => 'Payés en retard', 'couleur' => 'bg-amber-400']]" />
            </div>
            <div class="carte p-4">
                <h2 class="mb-3 font-semibold text-slate-900">Évolution du nombre de membres</h2>
                <x-graphique-barres format="nombre" :donnees="collect($global['effectifSerie'])->map(fn ($s) => ['libelle' => $s['libelle'], 'valeurs' => ['e' => $s['effectif']]])->all()"
                    :series="[['cle' => 'e', 'nom' => 'Membres en activité (fin de mois)', 'couleur' => 'bg-primaire-400']]" />
            </div>
        </section>

        {{-- Actions rapides et derniers paiements --}}
        <section class="mt-3 grid gap-3 lg:grid-cols-3">
            <div class="carte p-4">
                <h2 class="mb-3 font-semibold text-slate-900">Actions rapides</h2>
                <div class="grid grid-cols-2 gap-2">
                    @can('paiements.creer')<a href="{{ route('paiements.create') }}" class="btn-or col-span-2"><x-icone nom="plus" /> Enregistrer un paiement</a>@endcan
                    @can('cotisations.voir')<a href="{{ route('cotisations.index') }}" class="btn-secondaire">Cotisations</a>@endcan
                    @can('impayes.voir')<a href="{{ route('impayes') }}" class="btn-secondaire">Impayés</a>@endcan
                    @can('membres.creer')<a href="{{ route('membres.create') }}" class="btn-secondaire">Nouveau membre</a>@endcan
                    @can('rapports.voir')<a href="{{ route('rapports') }}" class="btn-secondaire">Rapports</a>@endcan
                </div>
            </div>
            @if ($global['derniers']->isNotEmpty())
                <div class="carte lg:col-span-2">
                    <div class="flex items-center justify-between px-4 pt-4 pb-2">
                        <h2 class="font-semibold text-slate-900">Derniers paiements</h2>
                        <a href="{{ route('paiements.index') }}" class="btn-lien text-sm">Tout voir</a>
                    </div>
                    <ul class="divide-y divide-slate-100">
                        @foreach ($global['derniers'] as $p)
                            <li>
                                <a href="{{ route('paiements.show', $p) }}" class="flex min-h-14 items-center gap-3 px-4 py-2 hover:bg-slate-50">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primaire-100 text-xs font-bold text-primaire-800">{{ $p->membre->initiales() }}</span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium {{ $p->estAnnule() ? 'text-slate-400 line-through' : '' }}">{{ $p->membre->nom_complet }}</span>
                                        <span class="block text-xs text-slate-500">{{ $p->date_paiement->format('d/m/Y') }} · {{ $p->modePaiement->nom }} · {{ $p->numero_recu }}</span>
                                    </span>
                                    <span class="text-sm font-semibold tabular-nums {{ $p->estAnnule() ? 'text-slate-400 line-through' : '' }}">{{ fcfa($p->montant) }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>
    @endif

    @if (! $global && ! $perso)
        <div class="carte"><x-vide message="Votre compte n'est rattaché à aucune fiche membre. Contactez un administrateur." /></div>
    @endif
</div>
