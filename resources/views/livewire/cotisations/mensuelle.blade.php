<div>
    <x-chargement />
    <x-entete-page titre="Cotisations du mois" :sous-titre="'Échéance le '.$stats['echeance']->format('d/m/Y').' · '.fcfa(parametre('cotisation_montant')).' par membre'">
        <x-slot:actions>
            @can('cotisations.generer')
                <button type="button" wire:click="generer" class="btn-secondaire" wire:confirm="Générer les cotisations manquantes de {{ $periodeObj->libelle() }} pour les membres redevables ?">Générer le mois</button>
            @endcan
        </x-slot:actions>
    </x-entete-page>

    {{-- Sélecteur de période --}}
    <div class="carte mb-3 flex items-center justify-between gap-2 p-2">
        <button type="button" wire:click="changerPeriode(-1)" class="btn-icone" aria-label="Mois précédent"><x-icone nom="chevron-gauche" class="size-6" /></button>
        <label class="flex flex-1 flex-col items-center">
            <span class="sr-only">Période</span>
            <input type="month" wire:model.live="periode" class="min-h-11 rounded-xl border-0 bg-transparent text-center text-base font-bold focus:ring-2 focus:ring-primaire-600/20">
        </label>
        <button type="button" wire:click="changerPeriode(1)" class="btn-icone" aria-label="Mois suivant"><x-icone nom="chevron-droite" class="size-6" /></button>
    </div>

    {{-- Indicateurs (§7.2) --}}
    <div class="mb-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Attendu" :valeur="fcfa($stats['attendu'])" :sous="$stats['total'].' membres'" />
        <x-stat label="Encaissé" :valeur="fcfa($stats['encaisse'])" :sous="'Taux '.str_replace('.', ',', $stats['taux']).' %'" />
        <x-stat label="Reste à recouvrer" :valeur="fcfa($stats['reste'])" />
        <x-stat label="À jour / retard / impayés" :valeur="$stats['a_jour'].' / '.$stats['en_retard'].' / '.$stats['impayes']" />
    </div>

    {{-- Filtres par état --}}
    @php
        $etats = ['' => 'Tous', 'a_jour' => 'À jour', 'paye' => 'Payés à temps', 'paye_retard' => 'En retard', 'impaye' => 'Impayés',
                  'partiel' => 'Partiels', 'a_venir' => 'À payer', 'annule' => 'Annulés / régularisés'];
    @endphp
    <div class="-mx-4 mb-3 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0">
        @foreach ($etats as $v => $l)
            <button type="button" wire:click="$set('etat', '{{ $v }}')"
                    class="min-h-10 shrink-0 rounded-full px-4 text-sm font-medium {{ $etat === $v ? 'bg-primaire-700 text-white' : 'border border-slate-200 bg-white text-slate-600' }}">{{ $l }}</button>
        @endforeach
    </div>
    <div class="mb-3 grid gap-2 sm:grid-cols-3">
        <input type="search" wire:model.live.debounce.300ms="recherche" placeholder="Rechercher un membre…" class="champ sm:col-span-2" aria-label="Rechercher un membre">
        <select wire:model.live="service" class="champ" aria-label="Service">
            <option value="">Tous les services</option>
            @foreach ($services as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
        </select>
    </div>

    <div class="carte overflow-hidden">
        @if ($cotisations->isEmpty())
            <x-vide :message="$stats['total'] ? 'Aucune cotisation pour ce filtre.' : 'Aucune cotisation générée pour '.$periodeObj->libelle().'.'" icone="calendrier" />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($cotisations as $c)
                    @php $e = $c->etat(); @endphp
                    <li wire:key="c-{{ $c->id }}" class="flex flex-wrap items-center gap-3 px-4 py-3 sm:flex-nowrap">
                        <a href="{{ route('membres.show', $c->membre_id) }}" class="flex min-w-0 flex-1 items-center gap-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white {{ $e->pastille() }}">{{ $e->icone() }}</span>
                            <span class="min-w-0">
                                <span class="block truncate font-semibold text-slate-900">{{ $c->membre->nom_complet }}</span>
                                <span class="block truncate text-xs text-slate-500">
                                    {{ $c->membre->matricule }} ·
                                    {{ fcfa($c->montant_paye) }} / {{ fcfa($c->montant_attendu) }}
                                    @if ($c->date_paiement) · payé le {{ $c->date_paiement->format('d/m') }}@endif
                                    @if ($c->joursRetard()) · <span class="text-red-700">{{ $c->joursRetard() }} j de retard</span>@endif
                                </span>
                            </span>
                        </a>
                        <div class="flex w-full items-center justify-between gap-2 pl-13 sm:w-auto sm:pl-0">
                            <x-badge-etat :etat="$e" />
                            <div class="flex items-center gap-1">
                                @if ($c->statut->estDue())
                                    @can('paiements.creer')
                                        <button type="button" wire:click="ouvrirSaisie({{ $c->id }})" class="btn-primaire min-h-10 px-3 text-xs" x-data :disabled="!$store.reseau.enLigne">Encaisser</button>
                                    @endcan
                                @endif
                                @can('cotisations.corriger')
                                    <div x-data="{ o: false }" class="relative">
                                        <button type="button" @click="o = !o" @click.outside="o = false" class="btn-icone size-10" aria-label="Autres actions">⋮</button>
                                        <div x-show="o" x-cloak class="absolute right-0 z-20 mt-1 w-52 overflow-hidden rounded-xl border border-slate-200 bg-white text-sm shadow-lg">
                                            @if ($c->statut->estDue())
                                                @if ($c->montant_paye === 0)
                                                    <button type="button" wire:click="ouvrirCorrection({{ $c->id }}, 'annuler')" @click="o = false" class="block min-h-11 w-full px-4 text-left hover:bg-slate-50">Annuler la cotisation</button>
                                                @endif
                                                <button type="button" wire:click="ouvrirCorrection({{ $c->id }}, 'regulariser')" @click="o = false" class="block min-h-11 w-full px-4 text-left hover:bg-slate-50">Régulariser (exonérer)</button>
                                            @elseif (in_array($c->statut, [\App\Enums\StatutCotisation::Annule, \App\Enums\StatutCotisation::Regularise], true))
                                                <button type="button" wire:click="ouvrirCorrection({{ $c->id }}, 'retablir')" @click="o = false" class="block min-h-11 w-full px-4 text-left hover:bg-slate-50">Rétablir</button>
                                            @else
                                                <p class="px-4 py-3 text-xs text-slate-500">Cotisation réglée : pour corriger, annulez le paiement concerné.</p>
                                            @endif
                                        </div>
                                    </div>
                                @endcan
                            </div>
                        </div>
                        @if ($c->motif)
                            <p class="w-full pl-13 text-xs text-slate-500 italic">Motif : {{ $c->motif }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
            {{ $cotisations->links() }}
        @endif
    </div>

    {{-- Saisie rapide d'un paiement --}}
    <x-modal nom="saisie-rapide" titre="Encaisser une cotisation">
        @if ($cotisationSaisie)
            <form wire:submit="encaisser" class="space-y-4">
                <div class="rounded-xl bg-primaire-50 p-3 text-sm">
                    <p class="font-semibold text-primaire-900">{{ $cotisationSaisie->membre->nom_complet }}</p>
                    <p class="text-primaire-800">{{ $cotisationSaisie->periode_libelle }} — reste dû {{ fcfa($cotisationSaisie->reste) }}</p>
                </div>
                <x-bloc-hors-ligne />
                <x-champ label="Montant (FCFA)" for="montant" :erreur="$errors->first('montant')" requis>
                    <input id="montant" type="number" inputmode="numeric" min="1" step="1" wire:model="montant" class="champ text-lg font-semibold">
                </x-champ>
                <div class="grid grid-cols-2 gap-3">
                    <x-champ label="Mode" for="modeId" :erreur="$errors->first('modeId')" requis>
                        <select id="modeId" wire:model="modeId" class="champ">
                            @foreach ($modes as $m)<option value="{{ $m->id }}">{{ $m->nom }}</option>@endforeach
                        </select>
                    </x-champ>
                    <x-champ label="Date" for="datePaiement" :erreur="$errors->first('datePaiement')" requis>
                        <input id="datePaiement" type="date" wire:model="datePaiement" max="{{ today()->toDateString() }}" class="champ">
                    </x-champ>
                </div>
                <x-champ label="Référence (transaction Wave / OM, n° chèque…)" for="reference" :erreur="$errors->first('reference')">
                    <input id="reference" type="text" wire:model="reference" class="champ" autocapitalize="characters">
                </x-champ>
                @if ($avertissements)
                    <div class="rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                        <p class="font-semibold">Doublon possible :</p>
                        <ul class="list-disc pl-5">@foreach ($avertissements as $a)<li>{{ $a }}</li>@endforeach</ul>
                        <label class="mt-2 flex min-h-11 items-center gap-2"><input type="checkbox" wire:model="confirmerDoublon" class="size-5 rounded"> Je confirme qu'il ne s'agit pas d'un doublon</label>
                    </div>
                @endif
                <button type="submit" class="btn-primaire w-full" wire:loading.attr="disabled" x-data :disabled="!$store.reseau.enLigne">Valider l'encaissement</button>
            </form>
        @endif
    </x-modal>

    {{-- Correction administrative tracée --}}
    @can('cotisations.corriger')
        <x-modal nom="correction" titre="Correction de cotisation">
            <form wire:submit="corriger" class="space-y-4">
                <p class="text-sm text-slate-600">
                    @switch($actionCorrection)
                        @case('annuler') La cotisation sera marquée « Annulée » (émise à tort). Elle reste visible dans l'historique. @break
                        @case('regulariser') Le reste dû sera soldé sans encaissement (exonération décidée par le bureau). @break
                        @case('retablir') La cotisation redeviendra exigible ; son statut sera recalculé d'après les paiements. @break
                    @endswitch
                </p>
                <x-champ label="Motif (obligatoire, conservé dans le journal d'audit)" for="motif-correction" :erreur="$errors->first('motif')" requis>
                    <textarea id="motif-correction" wire:model="motif" rows="3" class="champ"></textarea>
                </x-champ>
                <button type="submit" class="btn-danger w-full" x-data :disabled="!$store.reseau.enLigne">Confirmer</button>
            </form>
        </x-modal>
    @endcan
</div>
