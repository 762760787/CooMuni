<div>
    <x-chargement />
    <x-entete-page titre="Journal d'audit" sous-titre="Traçabilité des actions sensibles — en lecture seule, aucune entrée ne peut être modifiée ni supprimée." />

    <div class="carte mb-4 grid gap-2 p-3 sm:grid-cols-2 lg:grid-cols-5">
        <select wire:model.live="utilisateur" class="champ" aria-label="Utilisateur">
            <option value="">Tous les utilisateurs</option>
            <option value="systeme">Système / non connecté</option>
            @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}{{ $u->actif ? '' : ' (désactivé)' }}</option>@endforeach
        </select>
        <select wire:model.live="action" class="champ" aria-label="Action">
            <option value="">Toutes les actions</option>
            <optgroup label="Familles">
                @foreach ($familles as $f)<option value="{{ $f }}.*">{{ ucfirst(str_replace('_', ' ', $f)) }} (toutes)</option>@endforeach
            </optgroup>
            <optgroup label="Actions">
                @foreach ($actions as $cle => $libelle)<option value="{{ $cle }}">{{ $libelle }}</option>@endforeach
            </optgroup>
        </select>
        <input type="date" wire:model.live="du" class="champ" aria-label="Du">
        <input type="date" wire:model.live="au" class="champ" aria-label="Au">
        <input type="search" wire:model.live.debounce.300ms="recherche" placeholder="Description, IP…" class="champ" aria-label="Rechercher">
    </div>

    <div class="carte overflow-hidden">
        @if ($logs->isEmpty())
            <x-vide message="Aucune entrée pour ces critères." icone="journal" />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($logs as $l)
                    @php
                        $couleur = match (true) {
                            str_contains($l->action, 'annul') || str_contains($l->action, 'echec') || str_contains($l->action, 'blocage') || str_contains($l->action, 'desactiver') => 'bg-red-500',
                            str_contains($l->action, 'modifier') || str_contains($l->action, 'statut') || str_contains($l->action, 'permissions') || str_contains($l->action, 'regulariser') => 'bg-amber-500',
                            str_starts_with($l->action, 'auth.') => 'bg-slate-400',
                            default => 'bg-primaire-600',
                        };
                    @endphp
                    <li wire:key="a-{{ $l->id }}">
                        <button type="button" wire:click="voir({{ $l->id }})" class="flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-slate-50">
                            <span class="mt-1.5 size-2.5 shrink-0 rounded-full {{ $couleur }}"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-slate-900">{{ $l->libelleAction() }}</span>
                                @if ($l->description)<span class="block truncate text-sm text-slate-600">{{ $l->description }}</span>@endif
                                <span class="block text-xs text-slate-500">{{ $l->user?->libelleAffiche() ?? 'Système' }} · {{ $l->ip ?? 'console' }}</span>
                            </span>
                            <span class="shrink-0 text-right text-xs text-slate-500 tabular-nums">{{ $l->date_action->format('d/m/Y') }}<br>{{ $l->date_action->format('H:i:s') }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
            {{ $logs->links() }}
        @endif
    </div>

    <x-modal nom="detail-audit" titre="Détail de l'entrée" taille="max-w-2xl">
        @if ($detail)
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Action</dt><dd class="text-right font-medium">{{ $detail->libelleAction() }} <code class="text-xs text-slate-400">{{ $detail->action }}</code></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Date</dt><dd class="font-medium">{{ $detail->date_action->format('d/m/Y H:i:s') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Auteur</dt><dd class="font-medium">{{ $detail->user?->libelleAffiche() ?? 'Système' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Élément</dt><dd class="font-medium">{{ $detail->cible_type ?? '—' }}{{ $detail->cible_id ? ' #'.$detail->cible_id : '' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Adresse IP</dt><dd class="font-medium">{{ $detail->ip ?? '—' }}</dd></div>
                @if ($detail->description)<div><dt class="text-slate-500">Description</dt><dd>{{ $detail->description }}</dd></div>@endif
            </dl>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div>
                    <p class="mb-1 text-xs font-semibold text-slate-500 uppercase">Avant</p>
                    <pre class="max-h-64 overflow-auto rounded-xl bg-red-50 p-3 text-xs whitespace-pre-wrap text-red-900">{{ $detail->ancienne_valeur ? json_encode($detail->ancienne_valeur, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '—' }}</pre>
                </div>
                <div>
                    <p class="mb-1 text-xs font-semibold text-slate-500 uppercase">Après</p>
                    <pre class="max-h-64 overflow-auto rounded-xl bg-emerald-50 p-3 text-xs whitespace-pre-wrap text-emerald-900">{{ $detail->nouvelle_valeur ? json_encode($detail->nouvelle_valeur, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '—' }}</pre>
                </div>
            </div>
            <p class="mt-3 truncate text-xs text-slate-400" title="{{ $detail->user_agent }}">{{ $detail->user_agent }}</p>
        @endif
    </x-modal>
</div>
