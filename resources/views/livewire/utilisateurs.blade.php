<div>
    <x-chargement />
    <x-entete-page titre="Utilisateurs" :sous-titre="$users->total().' compte(s) — aucun compte n\'est supprimé, il est désactivé.'">
        <x-slot:actions>
            <button type="button" wire:click="nouveau" class="btn-primaire"><x-icone nom="plus" /> Nouvel utilisateur</button>
        </x-slot:actions>
    </x-entete-page>

    <div class="carte mb-4 grid gap-2 p-3 sm:grid-cols-3">
        <input type="search" wire:model.live.debounce.300ms="recherche" placeholder="Nom, identifiant, email, téléphone…" class="champ" aria-label="Rechercher">
        <select wire:model.live="filtreRole" class="champ" aria-label="Rôle">
            <option value="">Tous les rôles</option>
            @foreach ($roles as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach
        </select>
        <select wire:model.live="filtreActif" class="champ" aria-label="État">
            <option value="">Actifs et désactivés</option><option value="1">Actifs</option><option value="0">Désactivés</option>
        </select>
    </div>

    <div class="carte overflow-hidden">
        <ul class="divide-y divide-slate-100">
            @forelse ($users as $u)
                <li wire:key="u-{{ $u->id }}" class="flex flex-wrap items-center gap-3 px-4 py-3 sm:flex-nowrap {{ $u->actif ? '' : 'bg-slate-50' }}">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $u->actif ? 'bg-primaire-700 text-white' : 'bg-slate-200 text-slate-500' }}">{{ $u->initiales() }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold {{ $u->actif ? '' : 'text-slate-500' }}">{{ $u->name }} @unless ($u->actif)<span class="badge ml-1 bg-slate-200 text-slate-600 ring-slate-400/20">Désactivé</span>@endunless</p>
                        <p class="truncate text-xs text-slate-500">
                            <span class="font-mono">{{ $u->identifiant }}</span>{{ $u->email ? ' · '.$u->email : '' }}{{ $u->membre ? ' · fiche '.$u->membre->matricule : '' }}
                        </p>
                        <p class="text-xs text-slate-400">Dernière connexion : {{ $u->derniere_connexion_at?->format('d/m/Y H:i') ?? 'jamais' }}{{ $u->doit_changer_mdp ? ' · mot de passe temporaire' : '' }}</p>
                    </div>
                    <div class="flex w-full items-center justify-between gap-2 sm:w-auto">
                        <span class="badge bg-primaire-50 text-primaire-800 ring-primaire-600/20">{{ $u->roleNom() ?? '—' }}</span>
                        <div class="flex gap-1">
                            <button type="button" wire:click="editer({{ $u->id }})" class="btn-icone size-10" aria-label="Modifier {{ $u->name }}"><x-icone nom="crayon" /></button>
                            <button type="button" wire:click="reinitialiser({{ $u->id }})" wire:confirm="Générer un nouveau mot de passe temporaire pour {{ $u->name }} ? Ses sessions ouvertes seront fermées." class="btn-icone size-10" aria-label="Réinitialiser le mot de passe"><x-icone nom="cle" /></button>
                            @if ($u->id !== auth()->id())
                                <button type="button" wire:click="basculerActif({{ $u->id }})" wire:confirm="{{ $u->actif ? 'Désactiver' : 'Réactiver' }} le compte de {{ $u->name }} ?"
                                        class="btn-secondaire min-h-10 px-3 text-xs">{{ $u->actif ? 'Désactiver' : 'Réactiver' }}</button>
                            @endif
                        </div>
                    </div>
                </li>
            @empty
                <x-vide message="Aucun utilisateur." icone="utilisateurs" />
            @endforelse
        </ul>
        {{ $users->links() }}
    </div>

    <x-modal nom="utilisateur" :titre="$editionId ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur'">
        <form wire:submit="enregistrer" class="space-y-4">
            <x-champ label="Fiche membre liée (optionnel)" for="membreId" :erreur="$errors->first('membreId')" aide="Un compte lié donne accès à « Mon historique » et « Mes reçus ».">
                <input type="search" wire:model.live.debounce.300ms="rechercheMembre" placeholder="Filtrer les membres…" class="champ mb-2" aria-label="Filtrer les membres">
                <select id="membreId" wire:model.live="membreId" class="champ">
                    <option value="">Aucune</option>
                    @foreach ($membresLibres as $m)<option value="{{ $m->id }}">{{ $m->nom }} {{ $m->prenom }} ({{ $m->matricule }})</option>@endforeach
                </select>
            </x-champ>
            <x-champ label="Nom affiché" for="name" :erreur="$errors->first('name')" requis><input id="name" type="text" wire:model="name" class="champ"></x-champ>
            <x-champ label="Identifiant de connexion" for="identifiant" :erreur="$errors->first('identifiant')" requis>
                <input id="identifiant" type="text" wire:model="identifiant" autocapitalize="none" class="champ font-mono">
            </x-champ>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-champ label="Email" for="email" :erreur="$errors->first('email')"><input id="email" type="email" wire:model="email" class="champ"></x-champ>
                <x-champ label="Téléphone" for="telephone" :erreur="$errors->first('telephone')"><input id="telephone" type="tel" wire:model="telephone" class="champ"></x-champ>
            </div>
            <x-champ label="Rôle" for="role" :erreur="$errors->first('role')" requis>
                <select id="role" wire:model="role" class="champ">
                    @foreach ($roles as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach
                </select>
            </x-champ>
            @unless ($editionId)
                <p class="rounded-xl bg-slate-50 p-3 text-xs text-slate-600">Un mot de passe temporaire sera généré et affiché une seule fois. L'utilisateur devra le changer à sa première connexion.</p>
            @endunless
            <button type="submit" class="btn-primaire w-full">{{ $editionId ? 'Enregistrer' : 'Créer le compte' }}</button>
        </form>
    </x-modal>

    <x-modal nom="mdp" titre="Mot de passe temporaire">
        @if ($mdpAffiche)
            <div class="space-y-3 text-sm">
                <p>À remettre en main propre, après vérification de l'identité. <strong>Il ne sera plus affiché.</strong></p>
                <div class="rounded-xl bg-slate-50 p-4 font-mono">
                    <p>Identifiant : <strong>{{ $mdpPour }}</strong></p>
                    <p>Mot de passe : <strong class="select-all">{{ $mdpAffiche }}</strong></p>
                </div>
                <button type="button" class="btn-primaire w-full" wire:click="fermerMdp" x-on:click="$dispatch('fermer-modal')">J'ai noté ces informations</button>
            </div>
        @endif
    </x-modal>
</div>
