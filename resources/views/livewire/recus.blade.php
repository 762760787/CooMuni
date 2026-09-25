<div>
    <x-chargement />
    <x-entete-page :titre="$tous ? 'Reçus' : 'Mes reçus'" sous-titre="Touchez un reçu pour l'ouvrir en PDF ; téléchargement depuis l'aperçu." />

    <div class="carte mb-4 grid gap-2 p-3 sm:grid-cols-3">
        <input type="search" wire:model.live.debounce.300ms="recherche" placeholder="{{ $tous ? 'Membre, n° de reçu, référence…' : 'N° de reçu…' }}" class="champ" aria-label="Rechercher">
        <input type="date" wire:model.live="du" class="champ" aria-label="Du">
        <input type="date" wire:model.live="au" class="champ" aria-label="Au">
    </div>

    <div class="carte overflow-hidden">
        @if ($paiements->isEmpty())
            <x-vide message="Aucun reçu." icone="recu" />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($paiements as $p)
                    <li wire:key="r-{{ $p->id }}" class="flex items-center gap-3 px-4 py-3">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl {{ $p->estAnnule() ? 'bg-slate-100 text-slate-400' : 'bg-primaire-50 text-primaire-700' }}"><x-icone nom="recu" class="size-6" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold {{ $p->estAnnule() ? 'text-slate-400 line-through' : '' }}">
                                {{ $p->numero_recu }} · {{ fcfa($p->montant) }}
                            </p>
                            <p class="truncate text-xs text-slate-500">
                                @if ($tous){{ $p->membre->nom_complet }} · @endif{{ $p->libellePeriodes() }}
                            </p>
                            <p class="text-xs text-slate-500">{{ $p->date_paiement->format('d/m/Y') }} · {{ $p->modePaiement->nom }}{{ $p->estAnnule() ? ' · ANNULÉ' : '' }}</p>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <a href="{{ route('recus.pdf', $p) }}" target="_blank" class="btn-icone" aria-label="Aperçu du reçu {{ $p->numero_recu }}"><x-icone nom="oeil" /></a>
                            <a href="{{ route('recus.pdf', ['paiement' => $p, 'telecharger' => 1]) }}" class="btn-icone" aria-label="Télécharger le reçu {{ $p->numero_recu }}"><x-icone nom="telecharger" /></a>
                        </div>
                    </li>
                @endforeach
            </ul>
            {{ $paiements->links() }}
        @endif
    </div>
</div>
