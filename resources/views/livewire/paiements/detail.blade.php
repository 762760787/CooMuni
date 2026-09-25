<div>
    <x-chargement />
    <x-entete-page :titre="'Reçu '.$paiement->numero_recu" :sous-titre="$paiement->membre->nom_complet" :retour="route('paiements.index')">
        <x-slot:actions>
            <a href="{{ route('recus.pdf', $paiement) }}" target="_blank" class="btn-secondaire"><x-icone nom="oeil" /> Voir le reçu</a>
            <a href="{{ route('recus.pdf', ['paiement' => $paiement, 'telecharger' => 1]) }}" class="btn-primaire"><x-icone nom="telecharger" /> Télécharger</a>
        </x-slot:actions>
    </x-entete-page>

    @if ($nouveau && ! $paiement->estAnnule())
        <div class="mb-4 flex flex-wrap items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900">
            <x-icone nom="check" class="size-6" />
            <p class="flex-1 text-sm"><strong>Paiement enregistré.</strong> Le reçu est prêt ; l'historique du membre et le tableau de bord sont à jour.</p>
            <a href="{{ route('paiements.create') }}" class="btn-secondaire text-sm">Nouveau paiement</a>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <section class="carte p-5 lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm text-slate-500">Montant</p>
                    <p class="text-3xl font-bold tabular-nums {{ $paiement->estAnnule() ? 'text-slate-400 line-through' : 'text-slate-900' }}">{{ fcfa($paiement->montant) }}</p>
                </div>
                @if ($paiement->estAnnule())
                    <span class="badge bg-slate-200 text-slate-700 ring-slate-400/30">Annulé</span>
                @elseif ($paiement->annulationEnAttente())
                    <span class="badge bg-amber-100 text-amber-900 ring-amber-500/30">Annulation demandée</span>
                @else
                    <span class="badge bg-emerald-100 text-emerald-800 ring-emerald-600/20">Validé</span>
                @endif
            </div>

            <dl class="mt-5 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">Membre</dt><dd class="font-medium"><a class="text-primaire-700" href="{{ route('membres.show', $paiement->membre) }}">{{ $paiement->membre->nom_complet }}</a> ({{ $paiement->membre->matricule }})</dd></div>
                <div><dt class="text-slate-500">Date du paiement</dt><dd class="font-medium">{{ $paiement->date_paiement->translatedFormat('j F Y') }}</dd></div>
                <div><dt class="text-slate-500">Mode de paiement</dt><dd class="font-medium">{{ $paiement->modePaiement->nom }}</dd></div>
                <div><dt class="text-slate-500">Référence</dt><dd class="font-medium">{{ $paiement->reference ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Enregistré par</dt><dd class="font-medium">{{ $paiement->enregistrePar->libelleAffiche() }}</dd></div>
                <div><dt class="text-slate-500">Saisi le</dt><dd class="font-medium">{{ $paiement->created_at->format('d/m/Y à H:i') }}</dd></div>
                @if ($paiement->note)<div class="sm:col-span-2"><dt class="text-slate-500">Note</dt><dd>{{ $paiement->note }}</dd></div>@endif
            </dl>

            <h3 class="mt-6 mb-2 text-sm font-semibold text-slate-700">Ventilation sur les cotisations</h3>
            <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200">
                @foreach ($paiement->cotisations as $c)
                    <li class="flex min-h-12 items-center justify-between gap-3 px-3 py-2 text-sm">
                        <span>{{ $c->periode_libelle }}</span>
                        <span class="flex items-center gap-2"><strong class="tabular-nums">{{ fcfa($c->pivot->montant) }}</strong> <x-badge-etat :etat="$c->etat()" /></span>
                    </li>
                @endforeach
            </ul>

            @if ($paiement->paiementCorrige)
                <p class="mt-4 rounded-xl bg-sky-50 p-3 text-sm text-sky-900">Ce paiement corrige le reçu annulé
                    <a class="font-semibold underline" href="{{ route('paiements.show', $paiement->paiementCorrige) }}">{{ $paiement->paiementCorrige->numero_recu }}</a>.</p>
            @endif
            @if ($paiement->correction)
                <p class="mt-4 rounded-xl bg-sky-50 p-3 text-sm text-sky-900">Corrigé par le reçu
                    <a class="font-semibold underline" href="{{ route('paiements.show', $paiement->correction) }}">{{ $paiement->correction->numero_recu }}</a>.</p>
            @endif
        </section>

        <aside class="space-y-4">
            {{-- Annulation / correction tracée --}}
            <section class="carte p-4">
                <h2 class="mb-2 font-semibold">Annulation / correction</h2>
                @if ($paiement->estAnnule())
                    <div class="text-sm">
                        <p>Annulé le {{ $paiement->annule_le->format('d/m/Y à H:i') }} par {{ $paiement->annulePar?->libelleAffiche() }}.</p>
                        <p class="mt-1 text-slate-600 italic">« {{ $paiement->motif_annulation }} »</p>
                        @if ($paiement->demandeAnnulationPar)<p class="mt-1 text-xs text-slate-500">Demande initiale de {{ $paiement->demandeAnnulationPar->libelleAffiche() }}.</p>@endif
                    </div>
                    @can('paiements.creer')
                        @unless ($paiement->correction)
                            <a href="{{ route('paiements.create', ['membre' => $paiement->membre_id, 'corrige' => $paiement->id]) }}" class="btn-primaire mt-3 w-full">Saisir le paiement corrigé</a>
                        @endunless
                    @endcan
                @elseif ($paiement->annulationEnAttente())
                    <div class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">
                        <p><strong>{{ $paiement->demandeAnnulationPar?->libelleAffiche() }}</strong> a demandé l'annulation le {{ $paiement->demande_annulation_le->format('d/m/Y à H:i') }}.</p>
                        <p class="mt-1 italic">« {{ $paiement->demande_annulation_motif }} »</p>
                    </div>
                    @can('paiements.annuler')
                        <div class="mt-3 grid gap-2">
                            <button type="button" wire:click="ouvrir('valider')" class="btn-danger">Valider l'annulation</button>
                            <button type="button" wire:click="ouvrir('rejeter')" class="btn-secondaire">Rejeter la demande</button>
                        </div>
                    @else
                        <p class="mt-2 text-xs text-slate-500">En attente de validation par un administrateur.</p>
                    @endcan
                @else
                    <p class="text-sm text-slate-600">Un paiement n'est jamais supprimé ni modifié : en cas d'erreur, il est annulé avec un motif puis ressaisi correctement.</p>
                    <div class="mt-3 grid gap-2">
                        @can('paiements.annuler')
                            <button type="button" wire:click="ouvrir('annuler')" class="btn-danger" x-data :disabled="!$store.reseau.enLigne">Annuler ce paiement</button>
                        @elsecan('paiements.demander_annulation')
                            <button type="button" wire:click="ouvrir('demander')" class="btn-secondaire" x-data :disabled="!$store.reseau.enLigne">Demander l'annulation</button>
                        @endcan
                    </div>
                @endif
            </section>

            {{-- Trace d'audit --}}
            <section class="carte p-4">
                <h2 class="mb-3 font-semibold">Traçabilité</h2>
                <ol class="space-y-3 border-l-2 border-slate-200 pl-4 text-sm">
                    @foreach ($trace as $t)
                        <li>
                            <p class="font-medium">{{ $t->libelleAction() }}</p>
                            <p class="text-xs text-slate-500">{{ $t->date_action->format('d/m/Y H:i') }} — {{ $t->user?->libelleAffiche() ?? 'Système' }}</p>
                            @if ($m = ($t->nouvelle_valeur['motif'] ?? $t->nouvelle_valeur['motif_rejet'] ?? null))<p class="text-xs text-slate-600 italic">« {{ $m }} »</p>@endif
                        </li>
                    @endforeach
                </ol>
            </section>
        </aside>
    </div>

    <x-modal nom="annulation" :titre="match ($action) { 'demander' => 'Demander l\'annulation', 'rejeter' => 'Rejeter la demande', default => 'Annuler le paiement' }">
        <form wire:submit="confirmer" class="space-y-4">
            <x-bloc-hors-ligne />
            <p class="text-sm text-slate-600">
                @if ($action === 'rejeter')
                    Le paiement restera valide. Indiquez la raison du rejet (elle sera communiquée au demandeur).
                @elseif ($action === 'demander')
                    Votre demande sera soumise à un administrateur, qui pourra la valider ou la rejeter.
                @else
                    Le reçu {{ $paiement->numero_recu }} sera marqué « Annulé » (jamais supprimé) et les cotisations concernées seront recalculées.
                @endif
            </p>
            <x-champ label="Motif (obligatoire)" for="motif" :erreur="$errors->first('motif')" requis>
                <textarea id="motif" wire:model="motif" rows="3" class="champ"></textarea>
            </x-champ>
            <button type="submit" class="{{ $action === 'rejeter' || $action === 'demander' ? 'btn-primaire' : 'btn-danger' }} w-full" x-data :disabled="!$store.reseau.enLigne">Confirmer</button>
        </form>
    </x-modal>
</div>
