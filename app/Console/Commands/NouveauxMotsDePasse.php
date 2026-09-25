<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Audit;
use App\Services\MembreService;
use Illuminate\Console\Command;

/**
 * Attribue un nouveau mot de passe temporaire aux comptes actifs (tous, ou ceux
 * indiqués) et impose son changement à la première connexion. Utilisé lors de la
 * mise en ligne : les mots de passe de développement ne doivent pas servir en
 * production. Les mots de passe sont affichés une seule fois, jamais enregistrés.
 */
class NouveauxMotsDePasse extends Command
{
    protected $signature = 'coop:nouveaux-mots-de-passe
        {identifiants?* : Identifiants des comptes (par défaut : tous les comptes actifs)}
        {--force : Ne pas demander de confirmation}';

    protected $description = 'Génère de nouveaux mots de passe temporaires (changement imposé à la connexion).';

    public function handle(): int
    {
        $comptes = User::query()
            ->where('actif', true)
            ->when($this->argument('identifiants'), fn ($q, $ids) => $q->whereIn('identifiant', $ids))
            ->orderBy('identifiant')
            ->get();

        if ($comptes->isEmpty()) {
            $this->warn('Aucun compte actif correspondant.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Remplacer le mot de passe de {$comptes->count()} compte(s) ?")) {
            return self::FAILURE;
        }

        $lignes = [];
        foreach ($comptes as $compte) {
            $mdp = MembreService::motDePasseTemporaire();
            $compte->forceFill(['password' => $mdp, 'doit_changer_mdp' => true])->save();
            Audit::log('utilisateur.reinitialiser_mdp', $compte, null, null, $compte->identifiant.' (console)');
            $lignes[] = [$compte->identifiant, $compte->name, $mdp];
        }

        $this->table(['Identifiant', 'Nom', 'Mot de passe temporaire'], $lignes);
        $this->warn('Notez ces mots de passe maintenant : ils ne seront plus affichés. Un nouveau mot de passe sera demandé à la première connexion.');

        return self::SUCCESS;
    }
}
