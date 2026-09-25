<div class="carte p-5 sm:p-6">
    <h2 class="mb-1 text-lg font-bold text-slate-900">Connexion</h2>
    <p class="mb-5 text-sm text-slate-500">Identifiant (matricule ou nom d'utilisateur), email ou téléphone.</p>

    <form wire:submit="connecter" class="space-y-4" novalidate>
        <x-champ label="Identifiant" for="identifiant" :erreur="$errors->first('identifiant')">
            <input id="identifiant" type="text" wire:model="identifiant" autocomplete="username" autocapitalize="none" spellcheck="false"
                   class="champ @error('identifiant') champ-erreur @enderror" autofocus required>
        </x-champ>

        <x-champ label="Mot de passe" for="password" :erreur="$errors->first('password')" x-data="{ voir: false }">
            <div class="relative">
                <input id="password" :type="voir ? 'text' : 'password'" type="password" wire:model="password" autocomplete="current-password"
                       class="champ pr-12 @error('password') champ-erreur @enderror" required>
                <button type="button" @click="voir = !voir" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-slate-500"
                        :aria-label="voir ? 'Masquer le mot de passe' : 'Afficher le mot de passe'">
                    <x-icone nom="oeil" />
                </button>
            </div>
        </x-champ>

        <div class="flex items-center justify-between gap-2">
            <label class="flex min-h-11 items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" wire:model="remember" class="size-5 rounded border-slate-300 text-primaire-700 focus:ring-primaire-600">
                Rester connecté
            </label>
            <a href="{{ route('password.request') }}" class="btn-lien text-sm">Mot de passe oublié ?</a>
        </div>

        <button type="submit" class="btn-primaire w-full" wire:loading.attr="disabled" x-data :disabled="!$store.reseau.enLigne">
            <span wire:loading.remove wire:target="connecter">Se connecter</span>
            <span wire:loading wire:target="connecter">Connexion…</span>
        </button>
    </form>
</div>
