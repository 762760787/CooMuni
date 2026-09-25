<div>
    <x-chargement />
    <x-entete-page titre="Notifications" sous-titre="Notifications internes (email, SMS et WhatsApp prévus en V2/V3).">
        <x-slot:actions>
            <button type="button" wire:click="toutMarquerLu" class="btn-secondaire">Tout marquer comme lu</button>
            @can('notifications.diffuser')
                <button type="button" x-data @click="$dispatch('ouvrir-modal', 'diffuser')" class="btn-primaire">Diffuser une information</button>
            @endcan
        </x-slot:actions>
    </x-entete-page>

    <div class="carte overflow-hidden">
        @if ($notifications->isEmpty())
            <x-vide message="Aucune notification." icone="cloche" />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($notifications as $n)
                    <li wire:key="n-{{ $n->id }}">
                        <button type="button" wire:click="ouvrir({{ $n->id }})" class="flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-slate-50 {{ $n->lu ? '' : 'bg-primaire-50/60' }}">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $n->lu ? 'bg-slate-100 text-slate-500' : 'bg-primaire-700 text-white' }}">{{ $n->icone() }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm {{ $n->lu ? 'font-medium text-slate-700' : 'font-bold text-slate-900' }}">{{ $n->titre }}</span>
                                <span class="block text-sm text-slate-600">{{ $n->contenu }}</span>
                                <span class="block text-xs text-slate-400">{{ $n->created_at->diffForHumans() }}</span>
                            </span>
                            @unless ($n->lu)<span class="mt-2 size-2.5 shrink-0 rounded-full bg-primaire-600" aria-label="Non lue"></span>@endunless
                        </button>
                    </li>
                @endforeach
            </ul>
            {{ $notifications->links() }}
        @endif
    </div>

    @can('notifications.diffuser')
        <x-modal nom="diffuser" titre="Diffuser une information à tous les utilisateurs">
            <form wire:submit="diffuser" class="space-y-4">
                <x-champ label="Titre" for="titre" :erreur="$errors->first('titre')" requis><input id="titre" type="text" wire:model="titre" class="champ"></x-champ>
                <x-champ label="Message" for="contenu" :erreur="$errors->first('contenu')" requis><textarea id="contenu" wire:model="contenu" rows="4" class="champ"></textarea></x-champ>
                <button type="submit" class="btn-primaire w-full">Diffuser</button>
            </form>
        </x-modal>
    @endcan
</div>
