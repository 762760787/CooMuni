<div>
    <x-chargement />
    <x-entete-page :titre="$membre ? 'Modifier '.$membre->nom_complet : 'Nouveau membre'"
                   :retour="$membre ? route('membres.show', $membre) : route('membres.index')" />

    <form wire:submit="enregistrer" class="carte max-w-3xl space-y-5 p-4 sm:p-6" novalidate>
        <fieldset class="grid gap-4 sm:grid-cols-2">
            <legend class="mb-2 text-sm font-semibold text-primaire-800 sm:col-span-2">Identité</legend>
            <x-champ label="Matricule" for="matricule" :erreur="$errors->first('matricule')" requis aide="Proposé automatiquement ; modifiable.">
                <input id="matricule" type="text" wire:model.blur="matricule" class="champ font-mono @error('matricule') champ-erreur @enderror" autocapitalize="characters">
            </x-champ>
            <x-champ label="Sexe" for="sexe" :erreur="$errors->first('sexe')">
                <select id="sexe" wire:model="sexe" class="champ">
                    <option value="">Non renseigné</option><option value="H">Homme</option><option value="F">Femme</option>
                </select>
            </x-champ>
            <x-champ label="Prénom(s)" for="prenom" :erreur="$errors->first('prenom')" requis>
                <input id="prenom" type="text" wire:model.blur="prenom" autocomplete="given-name" class="champ @error('prenom') champ-erreur @enderror">
            </x-champ>
            <x-champ label="Nom" for="nom" :erreur="$errors->first('nom')" requis>
                <input id="nom" type="text" wire:model.blur="nom" autocomplete="family-name" class="champ uppercase @error('nom') champ-erreur @enderror">
            </x-champ>
            @if ($homonymes->isNotEmpty())
                <div class="rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 sm:col-span-2">
                    <p class="font-semibold">Homonyme(s) existant(s) :</p>
                    <ul class="mt-1 list-disc pl-5">
                        @foreach ($homonymes as $h)<li><a href="{{ route('membres.show', $h) }}" target="_blank" class="underline">{{ $h->nom_complet }} ({{ $h->matricule }})</a></li>@endforeach
                    </ul>
                    <label class="mt-2 flex min-h-11 items-center gap-2">
                        <input type="checkbox" wire:model="confirmerHomonyme" class="size-5 rounded border-amber-400 text-primaire-700">
                        Il s'agit bien d'une autre personne
                    </label>
                    @error('confirmerHomonyme')<p class="text-red-700">{{ $message }}</p>@enderror
                </div>
            @endif
        </fieldset>

        <fieldset class="grid gap-4 sm:grid-cols-2">
            <legend class="mb-2 text-sm font-semibold text-primaire-800 sm:col-span-2">Contact</legend>
            <x-champ label="Téléphone" for="telephone" :erreur="$errors->first('telephone')" aide="Doit être unique.">
                <input id="telephone" type="tel" inputmode="tel" wire:model.blur="telephone" autocomplete="tel" placeholder="77 000 00 00" class="champ @error('telephone') champ-erreur @enderror">
            </x-champ>
            <x-champ label="Email" for="email" :erreur="$errors->first('email')">
                <input id="email" type="email" inputmode="email" wire:model.blur="email" autocomplete="email" class="champ">
            </x-champ>
        </fieldset>

        <fieldset class="grid gap-4 sm:grid-cols-2">
            <legend class="mb-2 text-sm font-semibold text-primaire-800 sm:col-span-2">Rattachement</legend>
            <x-champ label="Fonction" for="fonction" :erreur="$errors->first('fonction')">
                <input id="fonction" type="text" wire:model="fonction" list="liste-fonctions" class="champ">
                <datalist id="liste-fonctions">@foreach ($fonctions as $f)<option value="{{ $f }}">@endforeach</datalist>
            </x-champ>
            <x-champ label="Service" for="service" :erreur="$errors->first('service')">
                <input id="service" type="text" wire:model="service" list="liste-services" class="champ">
                <datalist id="liste-services">@foreach ($services as $s)<option value="{{ $s }}">@endforeach</datalist>
            </x-champ>
            <x-champ label="Date d'adhésion" for="date_adhesion" :erreur="$errors->first('date_adhesion')" requis
                     :aide="$premierMois ? 'Première cotisation due : '.$premierMois.'.' : ($membre ? 'Une modification n\'affecte pas les cotisations déjà générées.' : null)">
                <input id="date_adhesion" type="date" wire:model.live="date_adhesion" max="{{ today()->toDateString() }}" class="champ">
            </x-champ>
            <x-champ label="Photo (optionnelle)" for="photo" :erreur="$errors->first('photo')" aide="JPG, PNG ou WebP — 2 Mo maximum.">
                <input id="photo" type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm file:mr-3 file:min-h-11 file:rounded-xl file:border-0 file:bg-primaire-50 file:px-4 file:font-semibold file:text-primaire-800">
                <div wire:loading wire:target="photo" class="mt-1 text-xs text-slate-500">Envoi de la photo…</div>
            </x-champ>
            <x-champ label="Observations" for="observations" :erreur="$errors->first('observations')" class="sm:col-span-2">
                <textarea id="observations" wire:model="observations" rows="2" class="champ"></textarea>
            </x-champ>
        </fieldset>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end">
            <a href="{{ $membre ? route('membres.show', $membre) : route('membres.index') }}" class="btn-secondaire">Annuler</a>
            <button type="submit" class="btn-primaire" wire:loading.attr="disabled" x-data :disabled="!$store.reseau.enLigne">
                <span wire:loading.remove wire:target="enregistrer">{{ $membre ? 'Enregistrer les modifications' : 'Créer le membre' }}</span>
                <span wire:loading wire:target="enregistrer">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
