<div>
    <x-chargement />
    <x-entete-page titre="Enregistrer un paiement" sous-titre="Enregistrement manuel — le reçu est généré automatiquement." :retour="url()->previous() !== url()->current() ? url()->previous() : route('dashboard')" />

    <div class="mx-auto max-w-2xl space-y-4">
        <x-bloc-hors-ligne />

        @if ($origine)
            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900">
                Correction du reçu annulé <strong>{{ $origine->numero_recu }}</strong> ({{ fcfa($origine->montant) }}) — le nouveau paiement y sera lié.
            </div>
        @endif

        {{-- Étape 1 : membre --}}
        <section class="carte p-4">
            <h2 class="mb-3 flex items-center gap-2 font-semibold"><span class="flex size-6 items-center justify-center rounded-full bg-primaire-700 text-xs text-white">1</span> Membre</h2>
            @if ($membre)
                <div class="flex items-center justify-between gap-3 rounded-xl bg-primaire-50 p-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primaire-700 text-sm font-bold text-white">{{ $membre->initiales() }}</span>
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $membre->nom_complet }}</p>
                            <p class="text-xs text-slate-600">{{ $membre->matricule }} · <x-badge-membre :statut="$membre->statut" /></p>
                        </div>
                    </div>
                    <button type="button" wire:click="changerMembre" class="btn-lien text-sm">Changer</button>
                </div>
            @else
                <div class="relative">
                    <x-icone nom="recherche" class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-slate-400" />
                    <input type="search" wire:model.live.debounce.300ms="rechercheMembre" placeholder="Nom, prénom, matricule ou téléphone" class="champ pl-10" autofocus aria-label="Rechercher le membre">
                </div>
                @error('membreId')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                <ul class="mt-2 divide-y divide-slate-100">
                    @foreach ($resultats as $r)
                        <li><button type="button" wire:click="choisirMembre({{ $r->id }})" class="flex min-h-12 w-full items-center gap-3 rounded-xl px-2 text-left hover:bg-primaire-50">
                            <span class="flex size-9 items-center justify-center rounded-full bg-primaire-100 text-xs font-bold text-primaire-800">{{ $r->initiales() }}</span>
                            <span class="flex-1"><span class="block text-sm font-semibold">{{ $r->nom_complet }}</span><span class="text-xs text-slate-500">{{ $r->matricule }}</span></span>
                        </button></li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($membre)
            <form wire:submit="enregistrer" class="space-y-4">
                {{-- Étape 2 : périodes --}}
                <section class="carte p-4">
                    <h2 class="mb-1 flex items-center gap-2 font-semibold"><span class="flex size-6 items-center justify-center rounded-full bg-primaire-700 text-xs text-white">2</span> Période(s) réglée(s)</h2>
                    <p class="mb-3 text-xs text-slate-500">Plusieurs mois possibles : le montant est affecté d'abord au mois le plus ancien.</p>
                    @error('periodes')<p class="mb-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    @if (empty($reglables))
                        <x-vide message="Aucune période à régler pour ce membre." icone="check" class="!py-6" />
                    @else
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($reglables as $r)
                                <label wire:key="p-{{ $r['periode'] }}" class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border px-3 py-2 has-[:checked]:border-primaire-600 has-[:checked]:bg-primaire-50 {{ $r['echue'] ? 'border-red-200' : 'border-slate-200' }}">
                                    <input type="checkbox" value="{{ $r['periode'] }}" wire:model.live="periodes" class="size-5 rounded border-slate-300 text-primaire-700">
                                    <span class="flex-1">
                                        <span class="block text-sm font-semibold">{{ $r['libelle'] }}</span>
                                        <span class="block text-xs {{ $r['echue'] ? 'text-red-700' : 'text-slate-500' }}">{{ $r['etat'] }} · {{ fcfa($r['reste']) }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </section>

                {{-- Étape 3 : montant et mode --}}
                <section class="carte space-y-4 p-4">
                    <h2 class="flex items-center gap-2 font-semibold"><span class="flex size-6 items-center justify-center rounded-full bg-primaire-700 text-xs text-white">3</span> Montant et mode de paiement</h2>
                    <x-champ label="Montant reçu (FCFA)" for="montant" :erreur="$errors->first('montant')" requis
                             :aide="$partielAutorise ? 'Un montant inférieur au total est enregistré comme paiement partiel.' : 'Le paiement partiel est désactivé dans les paramètres.'">
                        <input id="montant" type="number" inputmode="numeric" min="1" step="1" wire:model.blur="montant" class="champ text-xl font-bold tabular-nums">
                    </x-champ>
                    <div>
                        <span class="etiquette">Mode de paiement <span class="text-red-600">*</span></span>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach ($modes as $m)
                                <label class="flex min-h-12 cursor-pointer items-center justify-center rounded-xl border border-slate-200 px-2 text-center text-sm font-semibold has-[:checked]:border-primaire-600 has-[:checked]:bg-primaire-50 has-[:checked]:text-primaire-800">
                                    <input type="radio" wire:model.live="modeId" value="{{ $m->id }}" class="sr-only">{{ $m->nom }}
                                </label>
                            @endforeach
                        </div>
                        @error('modeId')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-champ label="Date du paiement" for="datePaiement" :erreur="$errors->first('datePaiement')" requis aide="Payé après l'échéance = « en retard ».">
                            <input id="datePaiement" type="date" wire:model="datePaiement" max="{{ today()->toDateString() }}" class="champ">
                        </x-champ>
                        <x-champ label="Référence{{ $modeCourant?->reference_requise ? '' : ' (facultative)' }}" for="reference" :erreur="$errors->first('reference')" :requis="(bool) $modeCourant?->reference_requise"
                                 aide="N° de transaction Wave / Orange Money, n° de chèque…">
                            <input id="reference" type="text" wire:model="reference" class="champ" autocapitalize="characters">
                        </x-champ>
                    </div>
                    <x-champ label="Note (facultative)" for="note" :erreur="$errors->first('note')">
                        <input id="note" type="text" wire:model="note" class="champ">
                    </x-champ>
                </section>

                @if ($avertissements)
                    <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="alert">
                        <p class="font-semibold">Attention : doublon possible</p>
                        <ul class="mt-1 list-disc pl-5">@foreach ($avertissements as $a)<li>{{ $a }}</li>@endforeach</ul>
                        <label class="mt-2 flex min-h-11 items-center gap-2 font-medium">
                            <input type="checkbox" wire:model="confirmerDoublon" class="size-5 rounded border-amber-400"> Je confirme qu'il s'agit d'un nouveau paiement
                        </label>
                    </div>
                @endif

                <div class="sticky bottom-20 z-20 rounded-2xl bg-white/95 p-3 shadow-lg ring-1 ring-slate-200 backdrop-blur lg:static lg:bg-transparent lg:p-0 lg:shadow-none lg:ring-0">
                    <button type="submit" class="btn-primaire w-full text-base" wire:loading.attr="disabled" x-data :disabled="!$store.reseau.enLigne">
                        <span wire:loading.remove wire:target="enregistrer">Valider · {{ fcfa((int) $montant) }}</span>
                        <span wire:loading wire:target="enregistrer">Enregistrement…</span>
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
