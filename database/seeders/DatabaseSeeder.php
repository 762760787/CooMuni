<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder par défaut : référentiels + membres réels (liste du personnel)
 * + données de DÉMONSTRATION fictives et comptes de test.
 *
 * Pour une installation de production sans données fictives :
 *   php artisan migrate --seed --seeder=ProductionSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ReferentielsSeeder::class,
            RolesPermissionsSeeder::class,
            MembresSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
