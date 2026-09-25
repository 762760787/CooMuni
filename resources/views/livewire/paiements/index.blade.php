<div>
    <x-chargement />
    <x-entete-page titre="Paiements" :sous-titre="$paiements->total().' opération(s) · '.fcfa($total).' encaissés (validés)'">
        <x-slot:actions>
            @can('paiements.creer')<a href="{{ route('paiements.create') }}" class="btn-or"><x-icone nom="plus" /> Enregistrer</a>@endcan
        </x-slot:actions>
    </x-entete-page>

    <div class="carte mb-4 grid gap-2 p-3 sm:grid-cols-2 lg:grid-cols-5">
        <input type="search" wire:model.live.debounce.300ms="recherche" placeholder="Membre, n° de reçu, référence…" class="champ lg:col-span-2" aria-label="Rechercher">
        <select wire:model.live="statut" class="champ" aria-label="Statut">
            <option value="">Tous les statuts</option>
            <option value="valide">Validés</option>
            <option value="demande">Annulation demandée</option>
            <option value="annule">Annulés</option>
        </select>
        <select wire:model.live="mode" class="champ" aria-label="Mode de paiement">
            <option value="">Tous les modes</option>
            @foreach ($modes as $m)<option value="{{ $m->id }}">{{ $m->nom }}</option>@endforeach
        </select>
        <div class="grid grid-cols-2 gap-2">
            <input type="date" wire:model.live="du" class="champ" aria-label="Du">
            <input type="date" wire:model.live="au" class="champ" aria-label="Au">
        </div>
    </div>

    <div class="carte overflow-hidden">
        @if ($paiements->isEmpty())
            <x-vide message="Aucun paiement trouvé." icone="billet" />
        @else
            @include('livewire.paiements.partials.liste', ['paiements' => $paiements, 'lienDetail' => true])
            {{ $paiements->links() }}
        @endif
    </div>
</div>
