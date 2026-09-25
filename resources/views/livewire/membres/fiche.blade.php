<div>
    <x-chargement />
    <x-entete-page :titre="$membre->nom_complet" :sous-titre="'Matricule '.$membre->matricule" :retour="$peutGerer ? route('membres.index') : route('dashboard')">
        <x-slot:actions>
            @can('paiements.creer')
                <a href="{{ route('paiements.create', ['membre' => $membre->id]) }}" class="btn-or"><x-icone nom="plus" /> Paiement</a>
            @endcan
            @can('membres.modifier')
                <a href="{{ route('membres.edit', $membre) }}" class="btn-secondaire"><x-icone nom="crayon" /> Modifier</a>
            @endcan
        </x-slot:actions>
    </x-entete-page>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Identité --}}
        <section class="carte p-5">
            <div class="flex items-center gap-4">
                @if ($membre->photo)
                    <img src="{{ route('membres.photo', $membre) }}" alt="Photo de {{ $membre->nom_complet }}" class="size-20 rounded-2xl object-cover">
                @else
                    <span class="flex size-20 items-center justify-center rounded-2xl bg-primaire-100 text-2xl font-bold text-primaire-800">{{ $membre->initiales() }}</span>
                @endif
                <div class="min-w-0">
                    <x-badge-membre :statut="$membre->statut" />
                    @if ($membre->user)
                        <p class="mt-1 text-xs text-slate-500">Compte : <span class="font-mono">{{ $membre->user->identifiant }}</span>{{ $membre->user->actif ? '' : ' (désactivé)' }}</p>
                    @endif
                </div>
            </div>
            <dl class="mt-5 space-y-3 text-sm">
                @foreach ([
                    'Sexe' => $membre->sexe === 'H' ? 'Homme' : ($membre->sexe === 'F' ? 'Femme' : null),
                    'Téléphone' => $membre->telephone,
                    'Email' => $membre->email,
                    'Fonction' => $membre->fonction,
                    'Service' => $membre->service,
                    'Date d\'adhésion' => $membre->date_adhesion->format('d/m/Y'),
                    'Date de sortie' => $membre->date_sortie?->format('d/m/Y'),
                ] as $label => $valeur)
                    @if ($valeur || in_array($label, ['Téléphone', 'Service'], true))
                        <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="text-right font-medium text-slate-800">
                                @if ($label === 'Téléphone' && $valeur)<a href="tel:{{ $valeur }}" class="text-primaire-700">{{ $valeur }}</a>@else{{ $valeur ?? '—' }}@endif
                            </dd>
                        </div>
                    @endif
                @endforeach
            </dl>
            @if ($membre->observations)
                <p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">{{ $membre->observations }}</p>
            @endif

            <div class="mt-4 flex flex-col gap-2">
                @can('membres.changer_statut')
                    <button type="button" class="btn-secondaire" x-data @click="$dispatch('ouvrir-modal', 'statut')">Changer le statut</button>
                @endcan
                @can('utilisateurs.gerer')
                    @if (! $membre->user && $membre->statut !== \App\Enums\StatutMembre::Decede)
                        <button type="button" class="btn-secondaire" wire:click="creerCompte" wire:confirm="Créer un compte d'accès « Membre » pour {{ $membre->nom_complet }} ?">
                            <x-icone nom="cle" /> Créer un accès membre
                        </button>
                    @endif
                @endcan
            </div>
        </section>

        <div class="space-y-4 lg:col-span-2">
            {{-- Statistiques individuelles (§7.3) --}}
            <section class="grid grid-cols-2 gap-3 sm:grid-cols-4" aria-label="Statistiques">
                <x-stat label="Mois payés" :valeur="$stats['mois_payes']" />
                <x-stat label="Retards" :valeur="$stats['retards']" />
                <x-stat label="Impayés" :valeur="$stats['impayes']" />
                <x-stat label="Total versé" :valeur="fcfa($stats['total_verse'])" />
            </section>
            @if ($stats['solde_du'] > 0)
                <div class="flex items-center gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <x-icone nom="alerte" class="size-5 shrink-0" />
                    Solde dû sur les échéances passées : <strong>{{ fcfa($stats['solde_du']) }}</strong>
                </div>
            @endif

            {{-- Cotisations récentes --}}
            <section class="carte">
                <div class="flex items-center justify-between px-4 pt-4 pb-2">
                    <h2 class="font-semibold text-slate-900">Dernières cotisations</h2>
                    <a href="{{ route('membres.historique', $membre) }}" class="btn-lien text-sm">Historique complet</a>
                </div>
                @forelse ($cotisations as $c)
                    <div class="flex min-h-12 items-center justify-between gap-3 border-t border-slate-100 px-4 py-2">
                        <div>
                            <p class="text-sm font-medium">{{ $c->periode_libelle }}</p>
                            <p class="text-xs text-slate-500">{{ fcfa($c->montant_paye) }} / {{ fcfa($c->montant_attendu) }}{{ $c->date_paiement ? ' · '.$c->date_paiement->format('d/m/Y') : '' }}</p>
                        </div>
                        <x-badge-etat :etat="$c->etat()" />
                    </div>
                @empty
                    <x-vide message="Aucune cotisation." />
                @endforelse
            </section>

            {{-- Paiements --}}
            <section class="carte">
                <h2 class="px-4 pt-4 pb-2 font-semibold text-slate-900">Derniers paiements</h2>
                @forelse ($paiements as $p)
                    <div class="flex min-h-12 items-center justify-between gap-3 border-t border-slate-100 px-4 py-2">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium {{ $p->estAnnule() ? 'text-slate-400 line-through' : '' }}">{{ fcfa($p->montant) }} · {{ $p->libellePeriodes() }}</p>
                            <p class="text-xs text-slate-500">{{ $p->date_paiement->format('d/m/Y') }} · {{ $p->modePaiement->nom }} · {{ $p->numero_recu }}{{ $p->estAnnule() ? ' · ANNULÉ' : '' }}</p>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <a href="{{ route('recus.pdf', $p) }}" target="_blank" class="btn-icone" aria-label="Reçu PDF"><x-icone nom="recu" /></a>
                            @can('paiements.voir')<a href="{{ route('paiements.show', $p) }}" class="btn-icone" aria-label="Détail"><x-icone nom="chevron-droite" /></a>@endcan
                        </div>
                    </div>
                @empty
                    <x-vide message="Aucun paiement enregistré." />
                @endforelse
            </section>

            {{-- Historique des statuts (§7.1) --}}
            <section class="carte p-4">
                <h2 class="mb-3 font-semibold text-slate-900">Historique des statuts</h2>
                <ol class="relative space-y-4 border-l-2 border-primaire-100 pl-5">
                    @foreach ($membre->historiqueStatuts as $h)
                        <li>
                            <span class="absolute -left-[7px] mt-1.5 size-3 rounded-full bg-primaire-500"></span>
                            <p class="text-sm font-medium">
                                @if ($h->ancien_statut){{ $h->ancien_statut->label() }} → @endif{{ $h->nouveau_statut->label() }}
                                <span class="font-normal text-slate-500">· effet le {{ $h->date_effet->format('d/m/Y') }}</span>
                            </p>
                            <p class="text-xs text-slate-500">{{ $h->motif }} — {{ $h->user?->libelleAffiche() ?? 'Système' }}, {{ $h->created_at->format('d/m/Y H:i') }}</p>
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>
    </div>

    @can('membres.changer_statut')
        <x-modal nom="statut" titre="Changer le statut du membre">
            <form wire:submit="changerStatut" class="space-y-4">
                <p class="text-sm text-slate-600">Statut actuel : <x-badge-membre :statut="$membre->statut" /></p>
                <x-champ label="Nouveau statut" for="nouveauStatut" :erreur="$errors->first('nouveauStatut')" requis>
                    <select id="nouveauStatut" wire:model="nouveauStatut" class="champ">
                        <option value="">— Choisir —</option>
                        @foreach ($statuts as $v => $l)
                            @if ($v !== $membre->statut->value)<option value="{{ $v }}">{{ $l }}</option>@endif
                        @endforeach
                    </select>
                </x-champ>
                <x-champ label="Date d'effet" for="dateEffet" :erreur="$errors->first('dateEffet')" requis
                         aide="Sortie / décès : les cotisations postérieures non réglées seront annulées (avec trace).">
                    <input id="dateEffet" type="date" wire:model="dateEffet" max="{{ today()->toDateString() }}" class="champ">
                </x-champ>
                <x-champ label="Motif" for="motif" :erreur="$errors->first('motif')" requis>
                    <textarea id="motif" wire:model="motif" rows="3" class="champ" placeholder="Motif du changement (obligatoire)"></textarea>
                </x-champ>
                <button type="submit" class="btn-primaire w-full" x-data :disabled="!$store.reseau.enLigne">Enregistrer le changement</button>
            </form>
        </x-modal>
    @endcan

    <x-modal nom="mdp-temporaire" titre="Compte membre créé">
        @if ($mdpTemporaire)
            <div class="space-y-3 text-sm">
                <p>Transmettez ces informations au membre, en main propre. <strong>Le mot de passe ne sera plus affiché.</strong></p>
                <div class="rounded-xl bg-slate-50 p-4 font-mono">
                    <p>Identifiant : <strong>{{ $membre->user?->identifiant }}</strong></p>
                    <p>Mot de passe temporaire : <strong class="select-all">{{ $mdpTemporaire }}</strong></p>
                </div>
                <p class="text-slate-500">Le membre devra choisir un nouveau mot de passe à sa première connexion.</p>
                <button type="button" class="btn-primaire w-full" wire:click="$set('mdpTemporaire', null)" x-on:click="$dispatch('fermer-modal')">J'ai noté ces informations</button>
            </div>
        @endif
    </x-modal>
</div>
