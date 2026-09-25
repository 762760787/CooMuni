<div>
    <x-chargement />
    <x-entete-page :titre="$personnel ? 'Mon historique de cotisations' : 'Historique de '.$membre->nom_complet"
                   :sous-titre="$membre->matricule.' · membre depuis le '.$membre->date_adhesion->format('d/m/Y')"
                   :retour="$personnel ? route('dashboard') : route('membres.show', $membre)">
        <x-slot:actions>
            @can('rapports.exporter')
                <a href="{{ route('rapports.export', ['type' => 'historique_membre', 'format' => 'pdf', 'membre_id' => $membre->id, 'annee' => $annee]) }}" class="btn-secondaire"><x-icone nom="telecharger" /> PDF</a>
            @endcan
        </x-slot:actions>
    </x-entete-page>

    {{-- Statistiques individuelles (§7.3) --}}
    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-5">
        <x-stat label="Mois payés" :valeur="$stats['mois_payes']" icone="check" />
        <x-stat label="Payés en retard" :valeur="$stats['retards']" icone="horloge" ton="ambre" />
        <x-stat label="Impayés" :valeur="$stats['impayes']" icone="alerte" ton="rouge" />
        <x-stat label="Total versé" :valeur="fcfa($stats['total_verse'])" icone="billet" ton="or" />
        <x-stat label="Solde dû (échu)" :valeur="fcfa($stats['solde_du'])" icone="caisse" :ton="$stats['solde_du'] ? 'rouge' : 'gris'" class="col-span-2 lg:col-span-1" />
    </div>

    {{-- Sélection de l'année --}}
    @if ($annees->count() > 1)
        <div class="mb-3 flex gap-2 overflow-x-auto">
            @foreach ($annees as $a)
                <button type="button" wire:click="$set('annee', {{ $a }})" class="min-h-10 rounded-full px-4 text-sm font-semibold {{ $annee == $a ? 'bg-primaire-700 text-white' : 'border border-slate-200 bg-white' }}">{{ $a }}</button>
            @endforeach
        </div>
    @endif

    {{-- Vue calendrier --}}
    <section class="carte mb-4 p-4" aria-label="Calendrier {{ $annee }}">
        <h2 class="mb-3 font-semibold">Année {{ $annee }}</h2>
        <div class="grid grid-cols-3 gap-2 sm:grid-cols-6 lg:grid-cols-12">
            @foreach (range(1, 12) as $m)
                @php $c = $parMois[$m] ?? null; $e = $c?->etat(); @endphp
                <div class="rounded-xl border p-2 text-center {{ $c ? 'border-slate-200' : 'border-dashed border-slate-200 opacity-60' }}">
                    <p class="text-xs font-semibold text-slate-500">{{ \App\Support\Periode::abreviation($m) }}</p>
                    <span class="mx-auto my-1 flex size-8 items-center justify-center rounded-full text-sm font-bold text-white {{ $e?->pastille() ?? 'bg-slate-100 !text-slate-400' }}">{{ $e?->icone() ?? '·' }}</span>
                    <p class="truncate text-[11px] text-slate-600">{{ $e?->label() ?? '—' }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Détail mois par mois --}}
    <section class="carte overflow-hidden">
        <h2 class="px-4 pt-4 pb-2 font-semibold">Détail des cotisations {{ $annee }}</h2>
        @forelse ($cotisations as $c)
            <details class="group border-t border-slate-100" wire:key="h-{{ $c->id }}">
                <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-3 px-4 py-2">
                    <div>
                        <p class="font-medium">{{ $c->periode_libelle }}</p>
                        <p class="text-xs text-slate-500">
                            {{ fcfa($c->montant_paye) }} / {{ fcfa($c->montant_attendu) }} · échéance {{ $c->date_echeance->format('d/m') }}
                            @if ($c->date_paiement) · payé le {{ $c->date_paiement->format('d/m/Y') }}@endif
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-badge-etat :etat="$c->etat()" />
                        <x-icone nom="chevron-bas" class="size-4 text-slate-400 transition group-open:rotate-180" />
                    </div>
                </summary>
                <div class="space-y-2 bg-slate-50/60 px-4 py-3 text-sm">
                    @if ($c->joursRetard())
                        <p class="text-red-700">Retard : {{ $c->joursRetard() }} jour(s) après l'échéance.</p>
                    @endif
                    @if ($c->motif)
                        <p class="text-slate-600 italic">Motif : {{ $c->motif }}</p>
                    @endif
                    @forelse ($c->paiements as $p)
                        <div class="flex items-center justify-between gap-2 rounded-xl bg-white p-2 ring-1 ring-slate-200">
                            <div class="{{ $p->estAnnule() ? 'text-slate-400 line-through' : '' }}">
                                <p class="font-medium">{{ fcfa($p->pivot->montant) }} · {{ $p->modePaiement->nom }}</p>
                                <p class="text-xs text-slate-500">{{ $p->date_paiement->format('d/m/Y') }} · reçu {{ $p->numero_recu }}{{ $p->estAnnule() ? ' · annulé' : '' }}</p>
                            </div>
                            <a href="{{ route('recus.pdf', $p) }}" target="_blank" class="btn-secondaire min-h-10 px-3 text-xs"><x-icone nom="recu" class="size-4" /> Reçu</a>
                        </div>
                    @empty
                        <p class="text-slate-500">Aucun paiement.</p>
                    @endforelse
                </div>
            </details>
        @empty
            <x-vide message="Aucune cotisation pour cette année." icone="calendrier" />
        @endforelse
    </section>
</div>
