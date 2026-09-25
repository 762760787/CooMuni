<?php

namespace Database\Seeders;

use App\Services\ImportMembres;
use Illuminate\Database\Seeder;

/**
 * Import des membres depuis la liste du personnel fournie par la commune
 * (database/seeders/data/liste_personnel.docx). Idempotent (matricule).
 *
 * La date d'adhésion réelle n'étant pas fournie, chaque membre reçoit la
 * première période de cotisation paramétrée (voir ASSUMPTIONS.md).
 *
 * Le fichier contient des données personnelles : il n'est pas versionné sur
 * GitHub. S'il est absent, utiliser la commande coop:importer-membres.
 */
class MembresSeeder extends Seeder
{
    public function run(): void
    {
        $fichier = database_path('seeders/data/liste_personnel.docx');
        if (! is_file($fichier)) {
            $this->command?->warn('Liste du personnel absente : aucun membre importé (commande coop:importer-membres).');

            return;
        }

        $import = app(ImportMembres::class);
        $lignes = $import->lire($fichier);
        $dateAdhesion = parametre('cotisation_periode_debut').'-01';

        $rapport = $import->importer($lignes, $dateAdhesion);

        $this->command?->info("Membres importés : {$rapport['crees']} (déjà présents : {$rapport['existants']}).");
        foreach ($rapport['avertissements'] as $a) {
            $this->command?->line("  ⚠ {$a}");
        }
    }
}
