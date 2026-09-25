<?php

namespace Database\Seeders;

use App\Support\CataloguePermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $nouvelles = [];
        foreach (CataloguePermissions::toutes() as $nom) {
            if (! Permission::where('name', $nom)->where('guard_name', 'web')->exists()) {
                $nouvelles[] = $nom;
            }
            Permission::findOrCreate($nom, 'web');
        }

        foreach (CataloguePermissions::parDefaut() as $role => $permissions) {
            $r = Role::findOrCreate($role, 'web');
            // Ne remplace pas une matrice déjà personnalisée, sauf pour l'administrateur (toujours complet).
            if ($r->permissions()->count() === 0 || $role === \App\Models\User::ROLE_ADMIN) {
                $r->syncPermissions($permissions);
            } elseif ($ajout = array_values(array_intersect($nouvelles, $permissions))) {
                // Permissions ajoutées au catalogue depuis l'installation : attribuées selon les valeurs par défaut.
                $r->givePermissionTo($ajout);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
