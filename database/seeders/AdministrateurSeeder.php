<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Compte administrateur initial (production). Le mot de passe provient de
 * ADMIN_INITIAL_PASSWORD ; s'il est absent, un mot de passe aléatoire est
 * généré et affiché une seule fois. Changement imposé à la première connexion.
 */
class AdministrateurSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('identifiant', 'admin')->exists()) {
            return;
        }
        $mdp = env('ADMIN_INITIAL_PASSWORD') ?: \App\Services\MembreService::motDePasseTemporaire();

        $admin = User::create([
            'name' => 'Administrateur',
            'identifiant' => 'admin',
            'password' => $mdp,
            'actif' => true,
            'doit_changer_mdp' => true,
        ]);
        $admin->assignRole(User::ROLE_ADMIN);

        $this->command?->warn("Compte administrateur créé — identifiant : admin — mot de passe temporaire : {$mdp}");
    }
}
