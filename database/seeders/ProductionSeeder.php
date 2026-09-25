<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Installation de production : paramètres, rôles, compte administrateur
 * initial et membres issus de la liste du personnel. Aucune donnée fictive.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ReferentielsSeeder::class,
            RolesPermissionsSeeder::class,
            AdministrateurSeeder::class,
            MembresSeeder::class,
        ]);
    }
}
