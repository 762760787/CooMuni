{{-- Liste des paiements : cartes sur mobile, tableau sur ordinateur (§11.3) --}}
<ul class="divide-y divide-slate-100 md:hidden">
    @foreach ($paiements as $p)
        <li wire:key="pm-{{ $p->id }}" class="flex items-center gap-3 px-4 py-3">
            <a href="{{ $lienDetail ? route('paiements.show', $p) : route('recus.pdf', $p) }}" @unless ($lienDetail) target="_blank" @endunless class="min-w-0 flex-1">
                <p class="truncate font-semibold {{ $p->estAnnule() ? 'text-slate-400 line-through' : 'text-slate-900' }}">{{ $p->membre->nom_complet }}</p>
                <p class="truncate text-xs text-slate-500">{{ $p->libellePeriodes() }}</p>
                <p class="text-xs text-slate-500">{{ $p->date_paiement->format('d/m/Y') }} · {{ $p->modePaiement->nom }} · {{ $p->numero_recu }}</p>
            </a>
            <div class="flex flex-col items-end gap-1">
                <span class="font-bold tabular-nums {{ $p->estAnnule() ? 'text-slate-400 line-through' : '' }}">{{ fcfa($p->montant) }}</span>
                @if ($p->estAnnule())<span class="badge bg-slate-100 text-slate-600 ring-slate-400/20">Annulé</span>
                @elseif ($p->annulationEnAttente())<span class="badge bg-amber-100 text-amber-900 ring-amber-500/30">Annulation demandée</span>@endif
                <a href="{{ route('recus.pdf', $p) }}" target="_blank" class="text-xs font-semibold text-primaire-700">Reçu PDF</a>
            </div>
        </li>
    @endforeach
</ul>
<div class="hidden overflow-x-auto md:block">
    <table class="tableau">
        <thead><tr><th>Reçu</th><th>Date</th><th>Membre</th><th>Périodes</th><th>Mode</th><th class="text-right">Montant</th><th>Statut</th><th class="sr-only">Actions</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @foreach ($paiements as $p)
                <tr wire:key="pt-{{ $p->id }}" class="{{ $p->estAnnule() ? 'text-slate-400' : '' }}">
                    <td class="font-mono text-xs">{{ $p->numero_recu }}</td>
                    <td>{{ $p->date_paiement->format('d/m/Y') }}</td>
                    <td class="font-medium">{{ $p->membre->nom_complet }}</td>
                    <td class="max-w-56 truncate text-xs" title="{{ $p->libellePeriodes() }}">{{ $p->libellePeriodes() }}</td>
                    <td>{{ $p->modePaiement->nom }}</td>
                    <td class="text-right font-semibold tabular-nums {{ $p->estAnnule() ? 'line-through' : '' }}">{{ fcfa($p->montant) }}</td>
                    <td>
                        @if ($p->estAnnule())<span class="badge bg-slate-100 text-slate-600 ring-slate-400/20">Annulé</span>
                        @elseif ($p->annulationEnAttente())<span class="badge bg-amber-100 text-amber-900 ring-amber-500/30">Annulation demandée</span>
                        @else<span class="badge bg-emerald-100 text-emerald-800 ring-emerald-600/20">Validé</span>@endif
                    </td>
                    <td class="text-right whitespace-nowrap">
                        <a href="{{ route('recus.pdf', $p) }}" target="_blank" class="btn-icone size-9" aria-label="Reçu PDF"><x-icone nom="recu" /></a>
                        @if ($lienDetail)<a href="{{ route('paiements.show', $p) }}" class="btn-icone size-9" aria-label="Détail"><x-icone nom="chevron-droite" /></a>@endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
