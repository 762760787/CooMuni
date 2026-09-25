<div>
    <x-chargement />
    <x-entete-page titre="Retards de paiement" sous-titre="Cotisations réglées après l'échéance (à ne pas confondre avec les impayés)." />

    <div class="mb-4 grid grid-cols-2 gap-3">
        <x-stat label="Paiements en retard" :valeur="$total" icone="horloge" ton="ambre" />
        <x-stat label="Retard moyen" :valeur="str_replace('.', ',', $moyenne).' jour(s)'" icone="calendrier" ton="gris" />
    </div>

    <div class="carte mb-4 grid gap-2 p-3 sm:grid-cols-3">
        <input type="search" wire:model.live.debounce.300ms="recherche" placeholder="Rechercher un membre…" class="champ" aria-label="Rechercher">
        <input type="month" wire:model.live="periode" class="champ" aria-label="Période">
        <div class="flex rounded-xl bg-slate-100 p-1">
            <button type="button" wire:click="$set('vue', 'liste')" class="min-h-10 flex-1 rounded-lg text-sm font-semibold {{ $vue === 'liste' ? 'bg-white shadow-sm' : 'text-slate-500' }}">Par cotisation</button>
            <button type="button" wire:click="$set('vue', 'membres')" class="min-h-10 flex-1 rounded-lg text-sm font-semibold {{ $vue === 'membres' ? 'bg-white shadow-sm' : 'text-slate-500' }}">Par membre</button>
        </div>
    </div>

    <div class="carte overflow-hidden">
        @if ($lignes->isEmpty())
            <x-vide message="Aucun paiement en retard pour ces critères." icone="check" />
        @elseif ($vue === 'membres')
            <ul class="divide-y divide-slate-100">
                @foreach ($lignes as $l)
                    @php $m = $membres[$l->membre_id]; @endphp
                    <li wire:key="rm-{{ $l->membre_id }}">
                        <a href="{{ route('membres.historique', $m) }}" class="flex min-h-14 items-center gap-3 px-4 py-3 hover:bg-slate-50">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-sm font-bold text-amber-800">{{ $l->nb }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold">{{ $m->nom_complet }}</span>
                                <span class="block text-xs text-slate-500">{{ $m->matricule }} · dernier retard : {{ periode_libelle($l->derniere) }}</span>
                            </span>
                            <span class="text-right text-xs text-slate-600">moy. {{ round($l->moyenne) }} j<br>max {{ (int) $l->maximum }} j</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            {{ $lignes->links() }}
        @else
            <ul class="divide-y divide-slate-100 md:hidden">
                @foreach ($lignes as $c)
                    <li wire:key="rc-{{ $c->id }}" class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $c->membre->nom_complet }}</p>
                            <p class="text-xs text-slate-500">{{ $c->periode_libelle }} · échéance {{ $c->date_echeance->format('d/m') }} · payé le {{ $c->date_paiement->format('d/m/Y') }}</p>
                        </div>
                        <span class="badge bg-amber-100 text-amber-900 ring-amber-500/30">+{{ $c->joursRetard() }} j</span>
                    </li>
                @endforeach
            </ul>
            <div class="hidden overflow-x-auto md:block">
                <table class="tableau">
                    <thead><tr><th>Membre</th><th>Période</th><th>Échéance</th><th>Payé le</th><th>Retard</th><th class="text-right">Montant</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($lignes as $c)
                            <tr wire:key="rt-{{ $c->id }}">
                                <td><a href="{{ route('membres.historique', $c->membre) }}" class="font-medium hover:text-primaire-700">{{ $c->membre->nom_complet }}</a></td>
                                <td>{{ $c->periode_libelle }}</td>
                                <td>{{ $c->date_echeance->format('d/m/Y') }}</td>
                                <td>{{ $c->date_paiement->format('d/m/Y') }}</td>
                                <td><span class="badge bg-amber-100 text-amber-900 ring-amber-500/30">{{ $c->joursRetard() }} jour(s)</span></td>
                                <td class="text-right tabular-nums">{{ fcfa($c->montant_paye) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $lignes->links() }}
        @endif
    </div>
</div>
