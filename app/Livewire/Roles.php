<?php

namespace App\Livewire;

use App\Livewire\Concerns\AvecRetours;
use App\Models\User;
use App\Services\Audit;
use App\Support\CataloguePermissions;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Écran 16 — Rôles et permissions : matrice rôle × permission.
 * Le rôle Administrateur garde toujours toutes les permissions (anti-verrouillage).
 */
#[Title('Rôles et permissions')]
class Roles extends Component
{
    use AvecRetours;

    /**
     * [id du rôle => [clé encodée => bool]] : les points des noms de permission sont
     * remplacés par « __ » (wire:model interprète les points comme des niveaux).
     *
     * @var array<int, array<string, bool>>
     */
    public array $matrice = [];

    public string $nouveauRole = '';

    public static function cle(string $permission): string
    {
        return str_replace('.', '__', $permission);
    }

    public function mount(): void
    {
        $this->exiger('roles.gerer');
        $this->charger();
    }

    private function charger(): void
    {
        $this->matrice = Role::with('permissions')->orderBy('id')->get()->mapWithKeys(fn (Role $r) => [
            $r->id => collect(CataloguePermissions::toutes())
                ->mapWithKeys(fn ($p) => [self::cle($p) => $r->permissions->contains('name', $p)])->all(),
        ])->all();
    }

    public function enregistrer(int $roleId): void
    {
        $this->exiger('roles.gerer');
        abort_unless(isset($this->matrice[$roleId]), 404);
        $r = Role::findOrFail($roleId);
        if ($r->name === User::ROLE_ADMIN) {
            $this->erreur('Le rôle Administrateur conserve toutes les permissions.');

            return;
        }
        $avant = $r->permissions->pluck('name')->sort()->values()->all();
        $apres = collect($this->matrice[$roleId])->filter()->keys()
            ->map(fn ($k) => str_replace('__', '.', $k))
            ->intersect(CataloguePermissions::toutes())->sort()->values()->all();

        $r->syncPermissions($apres);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $ajouts = array_values(array_diff($apres, $avant));
        $retraits = array_values(array_diff($avant, $apres));
        Audit::log('role.permissions', $r, ['retirees' => $retraits], ['ajoutees' => $ajouts],
            "Rôle {$r->name} : ".count($ajouts).' ajout(s), '.count($retraits).' retrait(s)');
        $this->succes("Permissions du rôle « {$r->name} » enregistrées.");
    }

    public function creer(): void
    {
        $this->exiger('roles.gerer');
        $this->validate(['nouveauRole' => ['required', 'string', 'min:3', 'max:50', 'unique:roles,name']], [], ['nouveauRole' => 'nom du rôle']);
        $r = Role::create(['name' => trim($this->nouveauRole), 'guard_name' => 'web']);
        Audit::log('role.creer', $r, null, ['name' => $r->name]);
        $this->reset('nouveauRole');
        $this->charger();
        $this->succes("Rôle « {$r->name} » créé : cochez ses permissions puis enregistrez.");
    }

    public function render()
    {
        return view('livewire.roles', [
            'groupes' => CataloguePermissions::GROUPES,
            'descriptions' => CataloguePermissions::DESCRIPTIONS_ROLES,
            'roles' => Role::withCount('users')->orderBy('id')->get()->keyBy('id'),
        ]);
    }
}
