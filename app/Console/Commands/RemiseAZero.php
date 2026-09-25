<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Audit;
use App\Services\Parametres;
use App\Support\Periode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Passage de la base de démonstration à la base réelle : efface les données
 * financières et l'historique de démonstration, en conservant les membres réels,
 * les comptes utilisateurs, les rôles et les paramètres. Une sauvegarde complète
 * est faite AVANT toute suppression (restauration : docs/SAUVEGARDE.md).
 */
class RemiseAZero extends Command
{
    protected $signature = 'coop:remise-a-zero
        {--debut= : Premier mois de cotisation (AAAA-MM), ex. 2026-10}
        {--force : Ne pas demander de confirmation}';

    protected $description = 'Efface les données de démonstration (cotisations, paiements, opérations, historique) en gardant membres réels et utilisateurs.';

    /** Membres et comptes fictifs créés par le DemoSeeder. */
    private const MATRICULES_DEMO = 'NGD-9%';
    private const COMPTES_DEMO = ['membre', 'ancien.tresorier'];

    public function handle(Parametres $parametres): int
    {
        $debut = $this->option('debut');
        if (! Periode::isValid($debut)) {
            $this->error('Indiquez le premier mois de cotisation : --debut=AAAA-MM (ex. --debut=2026-10).');

            return self::FAILURE;
        }

        $this->warn('Seront EFFACÉS : cotisations, paiements et reçus, opérations de caisse, journal d\'audit, notifications, membres fictifs NGD-9xx et comptes de démo ('.implode(', ', self::COMPTES_DEMO).').');
        $this->info('Seront CONSERVÉS : membres réels, autres comptes utilisateurs, rôles, paramètres, catégories, modes de paiement.');
        if (! $this->option('force') && ! $this->confirm('Confirmer la remise à zéro ?')) {
            return self::FAILURE;
        }

        // 1. Sauvegarde obligatoire avant toute suppression.
        if (Artisan::call('coop:sauvegarde') !== 0) {
            $this->error('Sauvegarde impossible : remise à zéro annulée. '.trim(Artisan::output()));

            return self::FAILURE;
        }
        $this->line(trim(Artisan::output()));

        $demo = DB::table('membres')->where('matricule', 'like', self::MATRICULES_DEMO)->pluck('id');
        $comptes = DB::table('users')->whereIn('identifiant', self::COMPTES_DEMO)->orWhereIn('membre_id', $demo)->pluck('id');
        $aujourdhui = today()->toDateString();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach (['paiement_cotisation', 'paiements', 'cotisations', 'operations_financieres', 'audit_logs', 'notifications', 'sequences', 'sessions'] as $table) {
                DB::table($table)->truncate();
            }
            DB::table('model_has_roles')->where('model_type', User::class)->whereIn('model_id', $comptes)->delete();
            DB::table('model_has_permissions')->where('model_type', User::class)->whereIn('model_id', $comptes)->delete();
            DB::table('users')->whereIn('id', $comptes)->delete();
            DB::table('membre_statuts')->whereIn('membre_id', $demo)->delete();
            DB::table('membres')->whereIn('id', $demo)->delete();
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // Adhésion enregistrée à la date de mise en service (la date réelle n'est pas connue).
        DB::table('membres')->update(['date_adhesion' => $aujourdhui, 'date_sortie' => null, 'statut' => 'actif', 'updated_at' => now()]);
        DB::table('membre_statuts')->update(['date_effet' => $aujourdhui, 'ancien_statut' => null, 'nouveau_statut' => 'actif', 'motif' => 'Adhésion']);

        // Comptes conservés : libellé « (démo) » retiré, changement de mot de passe imposé (mots de passe de démo publics).
        DB::table('users')->where('name', 'like', '%(démo)%')->get(['id', 'name'])->each(fn ($u) => DB::table('users')->where('id', $u->id)
            ->update(['name' => trim(str_replace('(démo)', '', $u->name))]));
        DB::table('users')->update(['doit_changer_mdp' => true, 'remember_token' => null]);

        DB::table('parametres')->where('cle', 'cotisation_periode_debut')->update(['valeur' => $debut]);
        DB::table('parametres')->where('cle', 'solde_initial')->update(['valeur' => '0']);
        $parametres->flush();
        Cache::flush();

        Audit::log('systeme.remise_a_zero', null, null, [
            'premier_mois' => $debut,
            'membres_conserves' => DB::table('membres')->count(),
            'comptes_conserves' => DB::table('users')->count(),
            'membres_demo_supprimes' => $demo->count(),
            'comptes_demo_supprimes' => $comptes->count(),
        ], 'Passage en base réelle : données de démonstration effacées (sauvegarde faite au préalable)');

        $this->info(sprintf('Terminé : %d membres et %d comptes conservés. Premier mois de cotisation : %s.',
            DB::table('membres')->count(), DB::table('users')->count(), Periode::fromString($debut)->libelle()));

        return self::SUCCESS;
    }
}
