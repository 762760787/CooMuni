<?php

namespace App\Console\Commands;

use App\Services\Audit;
use App\Services\Parametres;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Sauvegarde de la base (§20) : mysqldump compressé dans storage/app/private/backups,
 * rotation selon le paramètre « sauvegarde_retention ». Pour l'externalisation,
 * copier ce dossier vers un stockage distant (voir docs/SAUVEGARDE.md).
 */
class Sauvegarder extends Command
{
    protected $signature = 'coop:sauvegarde';

    protected $description = 'Sauvegarde la base de données (mysqldump + gzip) avec rotation.';

    public function handle(Parametres $parametres): int
    {
        $db = config('database.connections.'.config('database.default'));
        if (! in_array($db['driver'], ['mysql', 'mariadb'], true)) {
            $this->error('Sauvegarde prise en charge uniquement pour MySQL/MariaDB.');

            return self::FAILURE;
        }

        $dossier = storage_path('app/private/backups');
        File::ensureDirectoryExists($dossier);
        $fichier = $dossier.'/coop-'.now()->format('Y-m-d_His').'.sql.gz';

        $process = new Process([
            config('app.mysqldump_path'),
            '--host='.$db['host'], '--port='.$db['port'], '--user='.$db['username'],
            '--single-transaction', '--routines', '--no-tablespaces', '--default-character-set=utf8mb4',
            $db['database'],
        ], null, ['MYSQL_PWD' => (string) $db['password']], null, 600);

        $gz = gzopen($fichier, 'wb6');
        $process->run(function ($type, $buffer) use ($gz) {
            if ($type === Process::OUT) {
                gzwrite($gz, $buffer);
            }
        });
        gzclose($gz);

        if (! $process->isSuccessful()) {
            @unlink($fichier);
            $this->error('Échec de la sauvegarde : '.trim($process->getErrorOutput()));

            return self::FAILURE;
        }

        // Rotation
        $retention = max(1, (int) $parametres->get('sauvegarde_retention', 14));
        collect(File::glob($dossier.'/coop-*.sql.gz'))->sortDesc()->slice($retention)->each(fn ($f) => File::delete($f));

        Audit::log('sauvegarde.creer', 'Sauvegarde', null, ['fichier' => basename($fichier), 'taille' => filesize($fichier)]);
        $this->info('Sauvegarde créée : '.$fichier.' ('.round(filesize($fichier) / 1024, 1).' Ko)');

        return self::SUCCESS;
    }
}
