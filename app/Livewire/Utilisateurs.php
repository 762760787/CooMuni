<?php

namespace App\Livewire;

use App\Livewire\Concerns\AvecRetours;
use App\Models\Membre;
use App\Models\User;
use App\Services\Audit;
use App\Services\MembreService;
use App\Services\Notifications\Message;
use App\Services\Notifications\NotificationService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

/**
 * Écran 15 — Utilisateurs : liste, création, rôle, désactivation, réinitialisation.
 * Aucun compte n'est supprimé : un compte désactivé conserve ses traces (§8.2).
 */
#[Title('Utilisateurs')]
class Utilisateurs extends Component
{
    use AvecRetours, WithPagination;

    #[Url(except: '')]
    public string $recherche = '';

    #[Url(except: '')]
    public string $filtreRole = '';

    #[Url(except: '')]
    public string $filtreActif = '';

    public ?int $editionId = null;
    public string $name = '';
    public string $identifiant = '';
    public string $email = '';
    public string $telephone = '';
    public string $role = '';
    public string $membreId = '';
    public string $rechercheMembre = '';

    public ?string $mdpAffiche = null;
    public ?string $mdpPour = null;

    public function mount(): void
    {
        $this->exiger('utilisateurs.gerer');
    }

    public function updated($p): void
    {
        if (in_array($p, ['recherche', 'filtreRole', 'filtreActif'], true)) {
            $this->resetPage();
        }
    }

    public function nouveau(): void
    {
        $this->exiger('utilisateurs.gerer');
        $this->reset('editionId', 'name', 'identifiant', 'email', 'telephone', 'membreId', 'rechercheMembre');
        $this->role = User::ROLE_GESTIONNAIRE;
        $this->resetErrorBag();
        $this->dispatch('ouvrir-modal', 'utilisateur');
    }

    public function editer(int $id): void
    {
        $this->exiger('utilisateurs.gerer');
        $u = User::findOrFail($id);
        $this->editionId = $u->id;
        $this->name = $u->name;
        $this->identifiant = $u->identifiant;
        $this->email = (string) $u->email;
        $this->telephone = (string) $u->telephone;
        $this->role = (string) $u->roleNom();
        $this->membreId = (string) $u->membre_id;
        $this->rechercheMembre = '';
        $this->resetErrorBag();
        $this->dispatch('ouvrir-modal', 'utilisateur');
    }

    public function updatedMembreId(): void
    {
        if ($this->membreId && ! $this->editionId && $m = Membre::find($this->membreId)) {
            $this->name = $m->nom_complet;
            $this->identifiant = $m->matricule;
            $this->telephone = (string) $m->telephone;
            $this->email = (string) $m->email;
        }
    }

    public function enregistrer(NotificationService $notifications): void
    {
        $this->exiger('utilisateurs.gerer');
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'identifiant' => ['required', 'string', 'max:60', 'regex:/^[\w.\-@]+$/u', Rule::unique('users', 'identifiant')->ignore($this->editionId)],
            'email' => ['nullable', 'email:rfc', 'max:150', Rule::unique('users', 'email')->ignore($this->editionId)],
            'telephone' => ['nullable', 'string', 'max:30', Rule::unique('users', 'telephone')->ignore($this->editionId)],
            'role' => ['required', 'exists:roles,name'],
            'membreId' => ['nullable', 'exists:membres,id', Rule::unique('users', 'membre_id')->ignore($this->editionId)],
        ], ['identifiant.regex' => 'Lettres, chiffres, point, tiret ou @ uniquement.', 'membreId.unique' => 'Ce membre a déjà un compte.'],
            ['name' => 'nom affiché', 'membreId' => 'fiche membre']);

        $valeurs = [
            'name' => $data['name'],
            'identifiant' => $data['identifiant'],
            'email' => $data['email'] ? mb_strtolower($data['email']) : null,
            'telephone' => MembreService::normaliserTelephone($data['telephone']),
            'membre_id' => $data['membreId'] ?: null,
        ];

        if ($this->editionId) {
            $u = User::findOrFail($this->editionId);
            if ($u->id === auth()->id() && $data['role'] !== $u->roleNom()) {
                $this->addError('role', 'Vous ne pouvez pas modifier votre propre rôle.');

                return;
            }
            if ($u->estAdministrateur() && $data['role'] !== User::ROLE_ADMIN && $this->nbAdminsActifs() <= 1) {
                $this->addError('role', 'Il doit rester au moins un administrateur actif.');

                return;
            }
            $avant = $u->only(array_keys($valeurs)) + ['role' => $u->roleNom()];
            $u->update($valeurs);
            $u->syncRoles([$data['role']]);
            [$a, $b] = Audit::diff($avant, $valeurs + ['role' => $data['role']]);
            if ($b) {
                Audit::log('utilisateur.modifier', $u, $a, $b, $u->identifiant);
            }
            $this->succes('Utilisateur mis à jour.');
        } else {
            $mdp = MembreService::motDePasseTemporaire();
            $u = User::create($valeurs + ['password' => $mdp, 'actif' => true, 'doit_changer_mdp' => true]);
            $u->assignRole($data['role']);
            Audit::log('utilisateur.creer', $u, null, $valeurs + ['role' => $data['role']], $u->identifiant);
            $notifications->envoyer($u, new Message('information', 'Bienvenue',
                "Votre compte a été créé avec le rôle « {$data['role']} ».", route('dashboard')));
            $this->mdpAffiche = $mdp;
            $this->mdpPour = $u->identifiant;
        }
        $this->dispatch('fermer-modal');
        if ($this->mdpAffiche) {
            $this->dispatch('ouvrir-modal', 'mdp');
        }
    }

    public function basculerActif(int $id): void
    {
        $this->exiger('utilisateurs.gerer');
        $u = User::findOrFail($id);
        if ($u->id === auth()->id()) {
            $this->erreur('Vous ne pouvez pas désactiver votre propre compte.');

            return;
        }
        if ($u->actif && $u->estAdministrateur() && $this->nbAdminsActifs() <= 1) {
            $this->erreur('Impossible : c\'est le dernier administrateur actif.');

            return;
        }
        $u->update(['actif' => ! $u->actif]);
        if (! $u->actif) {
            // Fermeture immédiate des sessions ouvertes de cet utilisateur.
            \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $u->id)->delete();
        }
        Audit::log($u->actif ? 'utilisateur.activer' : 'utilisateur.desactiver', $u, ['actif' => ! $u->actif], ['actif' => $u->actif], $u->identifiant);
        $this->succes($u->actif ? 'Compte réactivé.' : 'Compte désactivé ; ses actions passées restent visibles dans l\'historique.');
    }

    public function reinitialiser(int $id): void
    {
        $this->exiger('utilisateurs.gerer');
        $u = User::findOrFail($id);
        $mdp = MembreService::motDePasseTemporaire();
        $u->update(['password' => $mdp, 'doit_changer_mdp' => true]);
        \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $u->id)->delete();
        Audit::log('utilisateur.reinitialiser_mdp', $u, null, null, $u->identifiant);
        $this->mdpAffiche = $mdp;
        $this->mdpPour = $u->identifiant;
        $this->dispatch('ouvrir-modal', 'mdp');
    }

    public function fermerMdp(): void
    {
        $this->reset('mdpAffiche', 'mdpPour');
    }

    private function nbAdminsActifs(): int
    {
        return User::role(User::ROLE_ADMIN)->where('actif', true)->count();
    }

    public function render()
    {
        $terme = trim($this->recherche);
        $users = User::with(['roles', 'membre'])
            ->when($terme !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$terme}%")
                ->orWhere('identifiant', 'like', "%{$terme}%")->orWhere('email', 'like', "%{$terme}%")->orWhere('telephone', 'like', "%{$terme}%")))
            ->when($this->filtreRole, fn ($q) => $q->role($this->filtreRole))
            ->when($this->filtreActif !== '', fn ($q) => $q->where('actif', $this->filtreActif === '1'))
            ->orderByDesc('actif')->orderBy('name')
            ->paginate(20);

        $membresLibres = Membre::where(fn ($q) => $q->whereDoesntHave('user')
            ->when($this->membreId, fn ($q) => $q->orWhere('id', (int) $this->membreId)))
            ->recherche($this->rechercheMembre)->orderBy('nom')->limit(50)->get(['id', 'nom', 'prenom', 'matricule']);

        return view('livewire.utilisateurs', [
            'users' => $users,
            'roles' => Role::orderBy('id')->pluck('name'),
            'membresLibres' => $membresLibres,
        ]);
    }
}
