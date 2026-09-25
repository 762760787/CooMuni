<?php

namespace App\Console\Commands;

use App\Services\ImportMembres;
use Illuminate\Console\Command;

class ImporterMembres extends Command
{
    protected $signature = 'coop:importer-membres
        {fichier : Chemin du fichier .docx ou .csv (colonnes : N°, Prénom, Nom, Téléphone)}
        {--date-adhesion= : Date d\'adhésion AAAA-MM-JJ (défaut : 1er jour de la première période de cotisation)}
        {--simulation : Affiche le résultat sans rien enregistrer}';

    protected $description = 'Importe des membres depuis la liste du personnel (idempotent sur le matricule).';

    public function handle(ImportMembres $import): int
    {
        $lignes = $import->lire($this->argument('fichier'));
        $date = $this->option('date-adhesion') ?: parametre('cotisation_periode_debut').'-01';
        $this->info(count($lignes).' ligne(s) lue(s).');

        $r = $import->importer($lignes, $date, (bool) $this->option('simulation'));

        $this->info(($this->option('simulation') ? '[SIMULATION] ' : '')."Créés : {$r['crees']} — déjà présents : {$r['existants']}");
        foreach ($r['avertissements'] as $a) {
            $this->warn(' • '.$a);
        }

        return self::SUCCESS;
    }
}
