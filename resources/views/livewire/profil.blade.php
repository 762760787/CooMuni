<div>
    <x-chargement />
    <x-entete-page titre="Mon profil" />

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="carte p-5">
            <div class="flex items-center gap-4">
                <span class="flex size-16 items-center justify-center rounded-2xl bg-primaire-700 text-xl font-bold text-white">{{ $user->initiales() }}</span>
                <div>
                    <p class="text-lg font-bold">{{ $user->name }}</p>
                    <p class="text-sm text-slate-500">Identifiant : <span class="font-mono">{{ $user->identifiant }}</span></p>
                    <span class="badge mt-1 bg-primaire-50 text-primaire-800 ring-primaire-600/20">{{ $user->roleNom() }}</span>
                </div>
            </div>
            @if ($user->membre)
                <p class="mt-4 text-sm text-slate-600">Fiche membre : <a href="{{ route('membres.show', $user->membre) }}" class="font-semibold text-primaire-700">{{ $user->membre->matricule }}</a></p>
            @endif

            <form wire:submit="enregistrerCoordonnees" class="mt-5 space-y-4">
                <h2 class="font-semibold">Coordonnées et préférences</h2>
                <x-champ label="Email" for="email" :erreur="$errors->first('email')">
                    <input id="email" type="email" inputmode="email" wire:model="email" autocomplete="email" class="champ">
                </x-champ>
                <x-champ label="Téléphone" for="telephone" :erreur="$errors->first('telephone')" aide="Peut servir d'identifiant de connexion.">
                    <input id="telephone" type="tel" inputmode="tel" wire:model="telephone" autocomplete="tel" class="champ">
                </x-champ>
                <label class="flex min-h-11 items-center gap-3 text-sm">
                    <input type="checkbox" wire:model="recevoirRappels" class="size-5 rounded border-slate-300 text-primaire-700">
                    Recevoir les rappels d'échéance de cotisation
                </label>
                <button type="submit" class="btn-primaire w-full sm:w-auto">Enregistrer</button>
            </form>
        </section>

        <div class="space-y-4">
            <form wire:submit="changerMotDePasse" class="carte space-y-4 p-5">
                <h2 class="font-semibold">Changer de mot de passe</h2>
                <x-champ label="Mot de passe actuel" for="actuel" :erreur="$errors->first('actuel')">
                    <input id="actuel" type="password" wire:model="actuel" autocomplete="current-password" class="champ">
                </x-champ>
                <x-champ label="Nouveau mot de passe" for="nouveau" :erreur="$errors->first('nouveau')" aide="8 caractères minimum, lettres et chiffres.">
                    <input id="nouveau" type="password" wire:model="nouveau" autocomplete="new-password" class="champ">
                </x-champ>
                <x-champ label="Confirmation" for="nouveau_confirmation">
                    <input id="nouveau_confirmation" type="password" wire:model="nouveau_confirmation" autocomplete="new-password" class="champ">
                </x-champ>
                <button type="submit" class="btn-primaire w-full sm:w-auto">Modifier le mot de passe</button>
            </form>

            {{-- Installation PWA (§12.3) --}}
            <section class="carte p-5" x-data>
                <h2 class="mb-2 font-semibold">Installer l'application sur mon téléphone</h2>
                <template x-if="$store.installation.disponible">
                    <button type="button" @click="$store.installation.installer()" class="btn-or w-full"><x-icone nom="installer" /> Installer maintenant</button>
                </template>
                <ul class="mt-2 space-y-2 text-sm text-slate-600">
                    <li><strong>Android (Chrome) :</strong> menu ⋮ → « Installer l'application » ou « Ajouter à l'écran d'accueil ».</li>
                    <li><strong>iPhone (Safari) :</strong> bouton Partager → « Sur l'écran d'accueil ».</li>
                </ul>
                <p class="mt-2 text-xs text-slate-500">L'application s'ouvre alors en plein écran, avec l'icône de la coopérative.</p>
            </section>
        </div>
    </div>
</div>
