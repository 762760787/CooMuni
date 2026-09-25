<?php

namespace App\Console\Commands;

use App\Services\CotisationService;
use App\Support\Periode;
use Illuminate\Console\Command;

class GenererCotisations extends Command
{
    protected $signature = 'coop:generer-cotisations {periode? : Période AAAA-MM (défaut : mois courant, avec rattrapage des mois manquants)}';

    protected $description = 'Génère les cotisations attendues des membres redevables (idempotent).';

    public function handle(CotisationService $service): int
    {
        $periode = $this->argument('periode');
        if ($periode !== null && ! Periode::isValid($periode)) {
            $this->error('Période invalide, format attendu : AAAA-MM.');

            return self::FAILURE;
        }

        $n = $periode ? $service->genererPeriode(Periode::fromString($periode)) : $service->genererJusqua();
        $this->info("{$n} cotisation(s) générée(s).");

        return self::SUCCESS;
    }
}
