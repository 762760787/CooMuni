<div>
    <x-chargement />
    <x-entete-page titre="Rôles et permissions" sous-titre="Les droits sont contrôlés par le serveur à chaque action, quel que soit l'affichage." />

    {{-- Un bloc repliable par rôle : lisible sur mobile, en deux colonnes sur grand écran --}}
    <div class="grid items-start gap-4 xl:grid-cols-2">
        @foreach ($matrice as $roleId => $perms)
            @php $r = $roles[$roleId]; $admin = $r->name === \App\Models\User::ROLE_ADMIN; @endphp
            <section class="carte overflow-hidden" wire:key="role-{{ $roleId }}" x-data="{ ouvert: false }">
                <button type="button" @click="ouvert = !ouvert" class="flex w-full items-start justify-between gap-3 p-4 text-left" :aria-expanded="ouvert">
                    <div>
                        <h2 class="font-semibold text-slate-900">{{ $r->name }} <span class="text-sm font-normal text-slate-500">· {{ $r->users_count }} utilisateur(s)</span></h2>
                        <p class="text-xs text-slate-500">{{ $descriptions[$r->name] ?? 'Rôle personnalisé.' }}</p>
                        <p class="mt-1 text-xs font-medium text-primaire-700">{{ collect($perms)->filter()->count() }} / {{ count($perms) }} permissions</p>
                    </div>
                    <span class="mt-1 text-slate-400 transition" :class="ouvert && 'rotate-180'"><x-icone nom="chevron-bas" /></span>
                </button>
                <div x-show="ouvert" x-cloak class="border-t border-slate-100 p-4">
                    @if ($admin)
                        <p class="mb-3 rounded-xl bg-primaire-50 p-3 text-xs text-primaire-900">Rôle verrouillé : l'administrateur conserve toutes les permissions pour éviter toute perte d'accès à l'administration.</p>
                    @endif
                    <div class="space-y-4">
                        @foreach ($groupes as $groupe => $liste)
                            <fieldset>
                                <legend class="mb-1 text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ $groupe }}</legend>
                                @foreach ($liste as $cle => $libelle)
                                    <label class="flex min-h-11 items-center gap-3 rounded-lg px-1 text-sm hover:bg-slate-50">
                                        <input type="checkbox" wire:model="matrice.{{ $roleId }}.{{ \App\Livewire\Roles::cle($cle) }}" @disabled($admin)
                                               class="size-5 shrink-0 rounded border-slate-300 text-primaire-700 disabled:opacity-60">
                                        <span class="flex-1">{{ $libelle }} <code class="hidden text-[11px] text-slate-400 sm:inline">{{ $cle }}</code></span>
                                    </label>
                                @endforeach
                            </fieldset>
                        @endforeach
                    </div>
                    @unless ($admin)
                        <button type="button" wire:click="enregistrer({{ $roleId }})" class="btn-primaire mt-4 w-full">Enregistrer les permissions « {{ $r->name }} »</button>
                    @endunless
                </div>
            </section>
        @endforeach
    </div>

    <form wire:submit="creer" class="carte mt-4 flex flex-col gap-2 p-4 sm:flex-row sm:items-end">
        <x-champ label="Nouveau rôle" for="nouveauRole" :erreur="$errors->first('nouveauRole')" class="flex-1">
            <input id="nouveauRole" type="text" wire:model="nouveauRole" class="champ" placeholder="ex. Commissaire aux comptes">
        </x-champ>
        <button type="submit" class="btn-secondaire">Créer le rôle</button>
    </form>
</div>
