<div>
    <x-chargement />
    @php $o = $operation; @endphp
    <x-entete-page :titre="'Opération '.$o->numero" :sous-titre="$o->categorie->nom" :retour="route('operations.index')" />

    <div class="grid gap-4 lg:grid-cols-3">
        <section class="carte p-5 lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm text-slate-500">{{ $o->estEntree() ? 'Entrée' : 'Sortie' }}</p>
                    <p class="text-3xl font-bold tabular-nums {{ $o->estAnnule() ? 'text-slate-400 line-through' : ($o->estEntree() ? 'text-emerald-700' : 'text-red-700') }}">{{ $o->estEntree() ? '+' : '−' }}{{ fcfa($o->montant) }}</p>
                </div>
                @if ($o->estAnnule())<span class="badge bg-slate-200 text-slate-700 ring-slate-400/30">Annulée</span>
                @elseif ($o->annulationEnAttente())<span class="badge bg-amber-100 text-amber-900 ring-amber-500/30">Annulation demandée</span>
                @else<span class="badge bg-emerald-100 text-emerald-800 ring-emerald-600/20">Validée</span>@endif
            </div>
            <dl class="mt-5 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">Date</dt><dd class="font-medium">{{ $o->date_operation->translatedFormat('j F Y') }}</dd></div>
                <div><dt class="text-slate-500">Catégorie</dt><dd class="font-medium">{{ $o->categorie->nom }}</dd></div>
                <div><dt class="text-slate-500">Mode de règlement</dt><dd class="font-medium">{{ $o->modePaiement?->nom ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Référence</dt><dd class="font-medium">{{ $o->reference ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Saisie par</dt><dd class="font-medium">{{ $o->enregistrePar->libelleAffiche() }}</dd></div>
                <div><dt class="text-slate-500">Saisie le</dt><dd class="font-medium">{{ $o->created_at->format('d/m/Y à H:i') }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-slate-500">Description</dt><dd>{{ $o->description }}</dd></div>
            </dl>
            @if ($o->justificatif)
                <a href="{{ route('operations.justificatif', $o) }}" target="_blank" class="btn-secondaire mt-5"><x-icone nom="trombone" /> Justificatif : {{ $o->justificatif_nom }}</a>
            @endif
        </section>

        <aside class="space-y-4">
            <section class="carte p-4">
                <h2 class="mb-2 font-semibold">Annulation</h2>
                @if ($o->estAnnule())
                    <p class="text-sm">Annulée le {{ $o->annule_le->format('d/m/Y à H:i') }} par {{ $o->annulePar?->libelleAffiche() }}.</p>
                    <p class="mt-1 text-sm text-slate-600 italic">« {{ $o->motif_annulation }} »</p>
                @elseif ($o->annulationEnAttente())
                    <div class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">
                        <p><strong>{{ $o->demandeAnnulationPar?->libelleAffiche() }}</strong> a demandé l'annulation le {{ $o->demande_annulation_le->format('d/m/Y à H:i') }}.</p>
                        <p class="mt-1 italic">« {{ $o->demande_annulation_motif }} »</p>
                    </div>
                    @can('operations.annuler')
                        <div class="mt-3 grid gap-2">
                            <button type="button" wire:click="ouvrir('valider')" class="btn-danger">Valider l'annulation</button>
                            <button type="button" wire:click="ouvrir('rejeter')" class="btn-secondaire">Rejeter la demande</button>
                        </div>
                    @endcan
                @else
                    <p class="text-sm text-slate-600">Aucune suppression possible : une erreur se corrige par une annulation motivée puis une nouvelle saisie.</p>
                    <div class="mt-3 grid">
                        @can('operations.annuler')
                            <button type="button" wire:click="ouvrir('annuler')" class="btn-danger" x-data :disabled="!$store.reseau.enLigne">Annuler l'opération</button>
                        @elsecan('operations.demander_annulation')
                            <button type="button" wire:click="ouvrir('demander')" class="btn-secondaire" x-data :disabled="!$store.reseau.enLigne">Demander l'annulation</button>
                        @endcan
                    </div>
                @endif
            </section>
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

    <x-modal nom="annulation-op" :titre="match ($action) { 'demander' => 'Demander l\'annulation', 'rejeter' => 'Rejeter la demande', default => 'Annuler l\'opération' }">
        <form wire:submit="confirmer" class="space-y-4">
            <x-bloc-hors-ligne />
            <x-champ label="Motif (obligatoire)" for="motif" :erreur="$errors->first('motif')" requis>
                <textarea id="motif" wire:model="motif" rows="3" class="champ"></textarea>
            </x-champ>
            <button type="submit" class="{{ in_array($action, ['rejeter', 'demander'], true) ? 'btn-primaire' : 'btn-danger' }} w-full" x-data :disabled="!$store.reseau.enLigne">Confirmer</button>
        </form>
    </x-modal>
</div>
