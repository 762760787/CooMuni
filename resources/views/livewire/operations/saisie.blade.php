<div>
    <x-chargement />
    <x-entete-page titre="Nouvelle opération" sous-titre="Entrée ou sortie de caisse (hors cotisations)." :retour="route('operations.index')" />

    <form wire:submit="enregistrer" class="carte mx-auto max-w-2xl space-y-4 p-4 sm:p-6" novalidate>
        <x-bloc-hors-ligne />

        <div class="grid grid-cols-2 gap-2 rounded-2xl bg-slate-100 p-1" role="radiogroup" aria-label="Type d'opération">
            <label class="flex min-h-12 cursor-pointer items-center justify-center gap-2 rounded-xl text-sm font-semibold has-[:checked]:bg-white has-[:checked]:text-emerald-700 has-[:checked]:shadow-sm">
                <input type="radio" wire:model.live="type" value="entree" class="sr-only"><x-icone nom="fleche-entree" /> Entrée
            </label>
            <label class="flex min-h-12 cursor-pointer items-center justify-center gap-2 rounded-xl text-sm font-semibold has-[:checked]:bg-white has-[:checked]:text-red-700 has-[:checked]:shadow-sm">
                <input type="radio" wire:model.live="type" value="sortie" class="sr-only"><x-icone nom="fleche-sortie" /> Sortie
            </label>
        </div>

        <x-champ label="Catégorie" for="categorieId" :erreur="$errors->first('categorieId')" requis>
            <select id="categorieId" wire:model="categorieId" class="champ">
                <option value="">— Choisir —</option>
                @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->nom }}{{ $c->restreinte ? ' (réservée)' : '' }}</option>@endforeach
            </select>
        </x-champ>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-champ label="Montant (FCFA)" for="montant" :erreur="$errors->first('montant')" requis>
                <input id="montant" type="number" inputmode="numeric" min="1" step="1" wire:model="montant" class="champ text-lg font-semibold">
            </x-champ>
            <x-champ label="Date" for="dateOperation" :erreur="$errors->first('dateOperation')" requis>
                <input id="dateOperation" type="date" wire:model="dateOperation" max="{{ today()->toDateString() }}" class="champ">
            </x-champ>
        </div>

        <x-champ label="Description / justification" for="description" :erreur="$errors->first('description')" requis>
            <textarea id="description" wire:model="description" rows="3" class="champ" placeholder="Objet de l'opération, bénéficiaire…"></textarea>
        </x-champ>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-champ label="Mode de règlement" for="modeId" :erreur="$errors->first('modeId')">
                <select id="modeId" wire:model="modeId" class="champ">
                    <option value="">Non précisé</option>
                    @foreach ($modes as $m)<option value="{{ $m->id }}">{{ $m->nom }}</option>@endforeach
                </select>
            </x-champ>
            <x-champ label="Référence (facture, pièce…)" for="reference" :erreur="$errors->first('reference')">
                <input id="reference" type="text" wire:model="reference" class="champ">
            </x-champ>
        </div>

        <x-champ label="Justificatif (optionnel)" for="justificatif" :erreur="$errors->first('justificatif')" aide="PDF, JPG, PNG ou WebP — 5 Mo maximum. Stocké de façon privée.">
            <input id="justificatif" type="file" wire:model="justificatif" accept="application/pdf,image/jpeg,image/png,image/webp" capture="environment"
                   class="block w-full text-sm file:mr-3 file:min-h-11 file:rounded-xl file:border-0 file:bg-primaire-50 file:px-4 file:font-semibold file:text-primaire-800">
            <div wire:loading wire:target="justificatif" class="mt-1 text-xs text-slate-500">Envoi du fichier…</div>
        </x-champ>

        @error('confirmerSolde')
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                <p>{{ $message }}</p>
                <label class="mt-2 flex min-h-11 items-center gap-2"><input type="checkbox" wire:model="confirmerSolde" class="size-5 rounded"> Je confirme cette sortie</label>
            </div>
        @enderror

        <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end">
            <a href="{{ route('operations.index') }}" class="btn-secondaire">Annuler</a>
            <button type="submit" class="btn-primaire" wire:loading.attr="disabled" x-data :disabled="!$store.reseau.enLigne">Enregistrer l'opération</button>
        </div>
    </form>
</div>
