<div>
    <x-chargement />
    <x-entete-page titre="Paramètres" sous-titre="Paramètres métier stockés en base : modifiables sans développement, chaque changement est tracé." />

    {{-- Onglets (défilement horizontal sur mobile) --}}
    <div class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0" role="tablist">
        @foreach ($groupes as $cle => $libelle)
            <button type="button" role="tab" wire:click="$set('onglet', '{{ $cle }}')" aria-selected="{{ $onglet === $cle ? 'true' : 'false' }}"
                    class="min-h-10 shrink-0 rounded-full px-4 text-sm font-medium {{ $onglet === $cle ? 'bg-primaire-700 text-white' : 'border border-slate-200 bg-white text-slate-600' }}">{{ $libelle }}</button>
        @endforeach
    </div>

    @if ($onglet === 'categories')
        <div class="carte overflow-hidden">
            <div class="flex items-center justify-between p-4">
                <p class="text-sm text-slate-600">Une catégorie utilisée n'est jamais supprimée : désactivez-la. « Réservée » = saisie limitée aux administrateurs.</p>
                <button type="button" wire:click="editerCategorie" class="btn-primaire shrink-0"><x-icone nom="plus" /> Ajouter</button>
            </div>
            <ul class="divide-y divide-slate-100 border-t border-slate-100">
                @foreach ($categories as $c)
                    <li class="flex min-h-14 items-center justify-between gap-3 px-4 py-2 {{ $c->actif ? '' : 'opacity-60' }}" wire:key="cat-{{ $c->id }}">
                        <div class="min-w-0">
                            <p class="font-medium">{{ $c->nom }}
                                <span class="badge ml-1 {{ $c->type === 'entree' ? 'bg-emerald-100 text-emerald-800 ring-emerald-600/20' : 'bg-red-100 text-red-800 ring-red-600/20' }}">{{ $c->type === 'entree' ? 'Entrée' : 'Sortie' }}</span>
                                @if ($c->restreinte)<span class="badge ml-1 bg-violet-100 text-violet-800 ring-violet-600/20">Réservée</span>@endif
                                @unless ($c->actif)<span class="badge ml-1 bg-slate-100 text-slate-600 ring-slate-400/20">Inactive</span>@endunless
                            </p>
                            <p class="text-xs text-slate-500">{{ $c->operations_count }} opération(s){{ $c->description ? ' · '.$c->description : '' }}</p>
                        </div>
                        <button type="button" wire:click="editerCategorie({{ $c->id }})" class="btn-icone" aria-label="Modifier {{ $c->nom }}"><x-icone nom="crayon" /></button>
                    </li>
                @endforeach
            </ul>
        </div>
    @elseif ($onglet === 'modes')
        <div class="carte overflow-hidden">
            <div class="flex items-center justify-between p-4">
                <p class="text-sm text-slate-600">Enregistrement manuel uniquement en V1 (aucune intégration opérateur).</p>
                <button type="button" wire:click="editerMode" class="btn-primaire shrink-0"><x-icone nom="plus" /> Ajouter</button>
            </div>
            <ul class="divide-y divide-slate-100 border-t border-slate-100">
                @foreach ($modes as $m)
                    <li class="flex min-h-14 items-center justify-between gap-3 px-4 py-2 {{ $m->actif ? '' : 'opacity-60' }}" wire:key="mode-{{ $m->id }}">
                        <div>
                            <p class="font-medium">{{ $m->nom }}
                                @if ($m->reference_requise)<span class="badge ml-1 bg-sky-100 text-sky-800 ring-sky-600/20">Référence obligatoire</span>@endif
                                @unless ($m->actif)<span class="badge ml-1 bg-slate-100 text-slate-600 ring-slate-400/20">Inactif</span>@endunless
                            </p>
                            <p class="text-xs text-slate-500">{{ $m->paiements_count }} paiement(s) · ordre {{ $m->ordre }}</p>
                        </div>
                        <button type="button" wire:click="editerMode({{ $m->id }})" class="btn-icone" aria-label="Modifier {{ $m->nom }}"><x-icone nom="crayon" /></button>
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <form wire:submit="enregistrer" class="carte max-w-3xl space-y-5 p-4 sm:p-6">
            @foreach ($parametres as $p)
                @php $id = 'param-'.$p->cle; $err = $errors->first('valeurs.'.$p->cle); @endphp
                @if ($p->type === 'image')
                    <x-champ :label="$p->libelle" :for="$id" :erreur="$errors->first('logo')" :aide="$p->description">
                        <div class="flex items-center gap-4">
                            <img src="{{ $logo ? $logo->temporaryUrl() : route('logo').'?v='.md5((string) ($valeurs['coop_logo'] ?? '')) }}" alt="Logo actuel" class="size-20 rounded-xl border border-slate-200 bg-white object-contain p-1">
                            <div class="flex-1 space-y-2">
                                <input id="{{ $id }}" type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm file:mr-3 file:min-h-11 file:rounded-xl file:border-0 file:bg-primaire-50 file:px-4 file:font-semibold file:text-primaire-800">
                                @if (! empty($valeurs['coop_logo']))<button type="button" wire:click="supprimerLogo" class="btn-lien text-sm">Rétablir le logo de la Mairie</button>@endif
                            </div>
                        </div>
                    </x-champ>
                @elseif ($p->type === 'bool')
                    <div>
                        <label class="flex min-h-11 items-start gap-3">
                            <input type="checkbox" class="mt-1 size-5 rounded border-slate-300 text-primaire-700" @checked(($valeurs[$p->cle] ?? '0') === '1')
                                   wire:change="$set('valeurs.{{ $p->cle }}', $event.target.checked ? '1' : '0')">
                            <span><span class="block text-sm font-medium text-slate-800">{{ $p->libelle }}</span>
                                @if ($p->description)<span class="block text-xs text-slate-500">{{ $p->description }}</span>@endif</span>
                        </label>
                        @if ($err)<p class="text-sm text-red-600">{{ $err }}</p>@endif
                    </div>
                @elseif ($p->type === 'select')
                    <x-champ :label="$p->libelle" :for="$id" :erreur="$err" :aide="$p->description">
                        <select id="{{ $id }}" wire:model="valeurs.{{ $p->cle }}" class="champ">
                            @foreach ($p->options ?? [] as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                        </select>
                    </x-champ>
                @else
                    <x-champ :label="$p->libelle" :for="$id" :erreur="$err" :aide="$p->description">
                        <input id="{{ $id }}" wire:model="valeurs.{{ $p->cle }}" class="champ"
                               @if ($p->type === 'int') type="number" inputmode="numeric" step="1" min="0" @elseif ($p->type === 'month') type="month" @else type="text" @endif>
                    </x-champ>
                @endif
            @endforeach

            @if ($onglet === 'cotisations')
                <p class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">
                    Un nouveau montant ou un nouveau jour d'échéance ne s'applique qu'aux mois <strong>pas encore générés</strong> :
                    les cotisations existantes conservent leurs valeurs (pas de rétroactivité).
                </p>
            @endif
            @if ($onglet === 'sauvegarde')
                <p class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600">
                    Sauvegarde automatique quotidienne à 2 h (commande <code>php artisan coop:sauvegarde</code>, voir docs/SAUVEGARDE.md).
                </p>
            @endif

            <div class="flex justify-end border-t border-slate-100 pt-4">
                <button type="submit" class="btn-primaire">Enregistrer</button>
            </div>
        </form>
    @endif

    <x-modal nom="categorie" :titre="$categorieId ? 'Modifier la catégorie' : 'Nouvelle catégorie'">
        <form wire:submit="enregistrerCategorie" class="space-y-4">
            <x-champ label="Nom" for="cat-nom" :erreur="$errors->first('categorie.nom')" requis><input id="cat-nom" type="text" wire:model="categorie.nom" class="champ"></x-champ>
            <x-champ label="Type" for="cat-type" :erreur="$errors->first('categorie.type')" requis>
                <select id="cat-type" wire:model="categorie.type" class="champ"><option value="entree">Entrée</option><option value="sortie">Sortie</option></select>
            </x-champ>
            <x-champ label="Description" for="cat-desc" :erreur="$errors->first('categorie.description')"><input id="cat-desc" type="text" wire:model="categorie.description" class="champ"></x-champ>
            <label class="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" wire:model="categorie.restreinte" class="size-5 rounded"> Réservée (saisie par les administrateurs uniquement)</label>
            <label class="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" wire:model="categorie.actif" class="size-5 rounded"> Active</label>
            <button type="submit" class="btn-primaire w-full">Enregistrer</button>
        </form>
    </x-modal>

    <x-modal nom="mode" :titre="$modeId ? 'Modifier le mode de paiement' : 'Nouveau mode de paiement'">
        <form wire:submit="enregistrerMode" class="space-y-4">
            <x-champ label="Nom" for="mode-nom" :erreur="$errors->first('mode.nom')" requis><input id="mode-nom" type="text" wire:model="mode.nom" class="champ"></x-champ>
            <x-champ label="Ordre d'affichage" for="mode-ordre" :erreur="$errors->first('mode.ordre')"><input id="mode-ordre" type="number" inputmode="numeric" wire:model="mode.ordre" class="champ"></x-champ>
            <label class="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" wire:model="mode.reference_requise" class="size-5 rounded"> Référence de transaction obligatoire</label>
            <label class="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" wire:model="mode.actif" class="size-5 rounded"> Actif</label>
            @error('mode.actif')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <button type="submit" class="btn-primaire w-full">Enregistrer</button>
        </form>
    </x-modal>
</div>
