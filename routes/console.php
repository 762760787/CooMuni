<?php

use Illuminate\Support\Facades\Schedule;

/*
| Planificateur (§18, §20). En production, une seule tâche cron suffit :
|   * * * * * cd /chemin/du/projet && php artisan schedule:run >> /dev/null 2>&1
*/

// Génération idempotente des cotisations du mois (avec rattrapage des mois manquants).
Schedule::command('coop:generer-cotisations')->dailyAt('00:10')->withoutOverlapping();

// Rappels d'échéance, rappel du jour J et notifications de retard (notifications internes).
Schedule::command('coop:rappels')->dailyAt('08:00')->withoutOverlapping();

// Sauvegarde quotidienne de la base avec rotation.
Schedule::command('coop:sauvegarde')->dailyAt('02:00')->withoutOverlapping();
