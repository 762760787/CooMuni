{{-- Voix (dictée + réponses parlées) : resources/js/voix-fatou.js. --}}
<div class="mx-auto flex max-w-2xl flex-col" x-data="dicteeFatou()" @fatou-parle.window="parler($event.detail)">
    <x-chargement cible="envoyer,confirmer" />

    <div class="mb-3 flex items-center gap-3">
        <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-primaire-600 to-primaire-900 text-lg font-bold text-or-300 shadow">{{ mb_substr($nom, 0, 1) }}</span>
        <div class="min-w-0 flex-1">
            <h1 class="text-xl font-bold text-slate-900">{{ $nom }}</h1>
            <p class="text-sm text-slate-500">
                @switch($moteur)
                    @case('claude') Agent IA (Claude) — raisonne et comprend le français et le wolof @break
                    @case('hybride') Moteur local + Claude si nécessaire — français et wolof @break
                    @default Moteur local gratuit — français et wolof, sans Internet
                @endswitch
            </p>
        </div>
        <button type="button" @click="basculerVoix()" class="btn-icone size-11" :class="parle ? 'bg-primaire-100 text-primaire-800 animate-pulse' : ''"
                :aria-label="voixActive ? 'Couper la voix de {{ $nom }}' : 'Activer la voix de {{ $nom }}'" :title="voixActive ? 'Réponses parlées : activées' : 'Réponses parlées : coupées'">
            <span x-text="voixActive ? '🔊' : '🔇'" class="text-xl" aria-hidden="true"></span>
        </button>
        @if ($historique)
            <button type="button" wire:click="recommencer" class="btn-secondaire text-sm">Nouvelle conversation</button>
        @endif
    </div>

    @if ($active && $moteur === 'local' && $moteurDemande !== 'local')
        <div class="mb-4 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            Le moteur « Claude » est choisi dans les paramètres, mais la clé <code>ANTHROPIC_API_KEY</code> n'est pas configurée : {{ $nom }} utilise le moteur local gratuit.
        </div>
    @endif

    @unless ($active)
        <div class="mb-4 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            {{ $nom }} est désactivée. Un administrateur peut la réactiver dans <a class="underline" href="{{ route('parametres', ['onglet' => 'assistant']) }}">Paramètres › Assistante IA</a>.
        </div>
    @endunless

    {{-- Conversation --}}
    <div class="carte mb-3 min-h-64 space-y-3 p-4" aria-live="polite">
        @if (! $historique)
            <div class="space-y-3 text-sm text-slate-600">
                <p>Bonjour ! Je suis <strong>{{ $nom }}</strong>. Dites-moi simplement ce qu'il faut encaisser : je retrouve le membre, les mois dus et je prépare le reçu. <strong>Rien n'est enregistré sans votre confirmation.</strong></p>
                <p class="font-medium text-slate-700">Exemples :</p>
                <div class="flex flex-col gap-2">
                    @foreach ([
                        'Fais un encaissement de Amy Tine aujourd\'hui',
                        'Amy Tine dafa fey ñaari weer tey ci Wave',
                        'Babacar Dione a payé 20 000 en espèces hier',
                        'Combien doit Daouda Sene ?',
                        "Qui n'a pas payé ?",
                        'Bilan du mois',
                    ] as $exemple)
                        <button type="button" wire:click="$set('saisie', @js($exemple))" class="min-h-11 rounded-xl border border-primaire-200 bg-primaire-50 px-3 py-2 text-left text-primaire-900 hover:bg-primaire-100">« {{ $exemple }} »</button>
                    @endforeach
                </div>
            </div>
        @endif

        @foreach ($historique as $i => $m)
            @if ($m['role'] === 'user')
                <div class="flex justify-end" wire:key="h{{ $i }}">
                    <p class="max-w-[85%] rounded-2xl rounded-br-md bg-primaire-700 px-4 py-2 text-sm whitespace-pre-line text-white">{{ $m['content'] }}</p>
                </div>
            @else
                <div class="flex items-start gap-2" wire:key="h{{ $i }}">
                    <span class="mt-1 flex size-7 shrink-0 items-center justify-center rounded-full bg-primaire-100 text-xs font-bold text-primaire-800">{{ mb_substr($nom, 0, 1) }}</span>
                    <p class="max-w-[85%] rounded-2xl rounded-bl-md bg-slate-100 px-4 py-2 text-sm whitespace-pre-line text-slate-800">{{ $m['content'] }}</p>
                </div>
            @endif
        @endforeach

        <div wire:loading.flex wire:target="envoyer" class="items-center gap-2 text-sm text-slate-500">
            <span class="flex size-7 items-center justify-center rounded-full bg-primaire-100 text-xs font-bold text-primaire-800">{{ mb_substr($nom, 0, 1) }}</span>
            {{ $nom }} réfléchit…
        </div>

        {{-- Carte de confirmation : l'encaissement n'est enregistré qu'ici --}}
        @if ($proposition)
            <div class="rounded-2xl border-2 border-or-400 bg-or-100/40 p-4">
                <p class="text-xs font-semibold tracking-wide text-or-600 uppercase">Encaissement à confirmer</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ fcfa($proposition['montant']) }}</p>
                <dl class="mt-2 space-y-1 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Membre</dt><dd class="text-right font-semibold">{{ $proposition['membre'] }} <span class="font-normal text-slate-500">({{ $proposition['matricule'] }})</span></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Mois</dt><dd class="text-right font-medium">{{ $proposition['periodes_libelle'] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Mode</dt><dd class="text-right font-medium">{{ $proposition['mode'] }}{{ $proposition['reference'] ? ' · réf. '.$proposition['reference'] : '' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Date</dt><dd class="text-right font-medium">{{ date_fr($proposition['date_paiement']) }}</dd></div>
                    @if ($proposition['montant'] < $proposition['total_du'])
                        <p class="rounded-lg bg-violet-50 px-2 py-1 text-violet-800">Paiement partiel : il restera {{ fcfa($proposition['total_du'] - $proposition['montant']) }} à payer.</p>
                    @endif
                </dl>
                @if ($proposition['avertissements'])
                    <div class="mt-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                        <p class="font-semibold">Doublon possible :</p>
                        <ul class="list-disc pl-5">@foreach ($proposition['avertissements'] as $a)<li>{{ $a }}</li>@endforeach</ul>
                        <label class="mt-2 flex min-h-11 items-center gap-2"><input type="checkbox" wire:model="confirmerDoublon" class="size-5 rounded"> Il s'agit bien d'un nouveau paiement</label>
                    </div>
                @endif
                <x-bloc-hors-ligne class="mt-3" />
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <button type="button" wire:click="abandonner" class="btn-secondaire">Annuler</button>
                    <button type="button" wire:click="confirmer" class="btn-primaire" wire:loading.attr="disabled" x-data :disabled="!$store.reseau.enLigne">
                        <x-icone nom="check" /> Confirmer
                    </button>
                </div>
            </div>
        @endif

        @if ($dernierRecu && ! $proposition)
            <div class="flex flex-wrap gap-2 pl-9">
                <a href="{{ route('recus.pdf', $dernierRecu['id']) }}" target="_blank" class="btn-secondaire min-h-10 text-xs"><x-icone nom="recu" class="size-4" /> Reçu {{ $dernierRecu['numero'] }}</a>
                <a href="{{ route('paiements.show', $dernierRecu['id']) }}" class="btn-lien text-xs">Voir le paiement</a>
            </div>
        @endif
    </div>

    {{-- Saisie : texte ou dictée vocale --}}
    <form wire:submit="envoyer" class="carte sticky bottom-20 flex items-end gap-2 p-2 lg:bottom-4">
        <label for="saisie-ia" class="sr-only">Votre demande</label>
        <textarea id="saisie-ia" wire:model="saisie" x-ref="saisie" rows="2" maxlength="1000"
                  placeholder="Ex. : Amy Tine dafa fey tey, 10 000 cash"
                  @keydown.enter.prevent="if (!$event.shiftKey) $wire.envoyer()"
                  class="champ min-h-12 flex-1 resize-none border-0 shadow-none focus:ring-0" @disabled(! $active)></textarea>
        <button type="button" @click="basculer()" class="btn-icone size-12"
                :class="ecoute ? 'bg-red-100 text-red-700 animate-pulse' : ''" :aria-label="ecoute ? 'Arrêter la dictée' : 'Dicter (français)'" @disabled(! $active)>
            <x-icone nom="micro" class="size-6" />
        </button>
        <button type="submit" class="btn-primaire size-12 px-0" aria-label="Envoyer" wire:loading.attr="disabled" x-data :disabled="!$store.reseau.enLigne" @disabled(! $active)>
            <x-icone nom="envoyer" class="size-5" />
        </button>
    </form>
    <p x-show="message" x-cloak x-text="message" role="status"
       class="mt-2 rounded-xl px-3 py-2 text-center text-sm" :class="ecoute ? 'bg-primaire-50 text-primaire-800' : 'bg-amber-50 text-amber-900'"></p>
    <div x-show="!message" class="mt-2 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-xs text-slate-500">
        <span>🎤 Parlez en français : la phrase part toute seule et {{ $nom }} vous répond à voix haute. Pour le wolof, écrivez.</span>
        <label class="inline-flex min-h-9 items-center gap-2">
            <input type="checkbox" :checked="envoiAuto" @change="basculerEnvoiAuto()" class="size-4 rounded border-slate-300 text-primaire-700">
            Envoi automatique après la dictée
        </label>
    </div>
</div>

