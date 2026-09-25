<div class="carte p-5 sm:p-6">
    <h2 class="mb-1 text-lg font-bold text-slate-900">Choisissez votre mot de passe</h2>
    <p class="mb-5 text-sm text-slate-500">Vous utilisez un mot de passe temporaire. Pour votre sécurité, définissez un mot de passe personnel
        (8 caractères minimum, avec des lettres et des chiffres).</p>

    <form wire:submit="enregistrer" class="space-y-4">
        <x-champ label="Mot de passe temporaire" for="actuel" :erreur="$errors->first('actuel')">
            <input id="actuel" type="password" wire:model="actuel" autocomplete="current-password" class="champ">
        </x-champ>
        <x-champ label="Nouveau mot de passe" for="nouveau" :erreur="$errors->first('nouveau')">
            <input id="nouveau" type="password" wire:model="nouveau" autocomplete="new-password" class="champ">
        </x-champ>
        <x-champ label="Confirmer le nouveau mot de passe" for="nouveau_confirmation">
            <input id="nouveau_confirmation" type="password" wire:model="nouveau_confirmation" autocomplete="new-password" class="champ">
        </x-champ>
        <button type="submit" class="btn-primaire w-full">Enregistrer</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-3">
        @csrf
        <button type="submit" class="btn-secondaire w-full">Se déconnecter</button>
    </form>
</div>
