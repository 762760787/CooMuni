<div class="carte p-5 sm:p-6">
    <h2 class="mb-1 text-lg font-bold text-slate-900">Mot de passe oublié</h2>

    @if ($envoye)
        <div class="rounded-xl bg-primaire-50 p-4 text-sm text-primaire-900">
            <p class="font-semibold">Demande enregistrée.</p>
            <p class="mt-1">Si un compte correspond à cet identifiant, un administrateur de la coopérative a été prévenu.
                Il vous remettra un mot de passe temporaire après vérification de votre identité ; vous devrez le changer à la première connexion.</p>
        </div>
        <a href="{{ route('login') }}" class="btn-primaire mt-5 w-full">Retour à la connexion</a>
    @else
        <p class="mb-5 text-sm text-slate-500">Indiquez votre identifiant, votre email ou votre téléphone. La demande sera transmise à un administrateur.</p>
        <form wire:submit="demander" class="space-y-4">
            <x-champ label="Identifiant, email ou téléphone" for="identifiant" :erreur="$errors->first('identifiant')">
                <input id="identifiant" type="text" wire:model="identifiant" autocomplete="username" autocapitalize="none" class="champ" autofocus>
            </x-champ>
            <button type="submit" class="btn-primaire w-full" wire:loading.attr="disabled">Envoyer la demande</button>
            <a href="{{ route('login') }}" class="btn-secondaire w-full">Annuler</a>
        </form>
    @endif
</div>
