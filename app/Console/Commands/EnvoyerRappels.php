<?php

namespace App\Console\Commands;

use App\Models\Cotisation;
use App\Models\User;
use App\Services\Notifications\Message;
use App\Services\Notifications\NotificationService;
use App\Services\Parametres;
use App\Support\Periode;
use Illuminate\Console\Command;

/**
 * Rappels internes (§18) : avant l'échéance, le jour de l'échéance, et
 * notification de retard après l'échéance. Chaque rappel n'est émis qu'une
 * fois par membre et par période (clé d'unicité).
 */
class EnvoyerRappels extends Command
{
    protected $signature = 'coop:rappels';

    protected $description = 'Envoie les rappels d\'échéance et notifications de retard (notifications internes).';

    public function handle(Parametres $parametres, NotificationService $notifications): int
    {
        if (! $parametres->bool('rappel_actif') && ! $parametres->bool('notification_retard_actif')) {
            $this->info('Rappels désactivés dans les paramètres.');

            return self::SUCCESS;
        }

        $periode = Periode::courante();
        $echeance = $periode->echeance($parametres->jourEcheance());
        $aujourdhui = today();
        $joursAvant = (int) $parametres->get('rappel_jours_avant', 0);

        $type = match (true) {
            $parametres->bool('rappel_actif') && $aujourdhui->isSameDay($echeance) => 'rappel_jour_echeance',
            $parametres->bool('rappel_actif') && $aujourdhui->lt($echeance) && $aujourdhui->diffInDays($echeance) <= $joursAvant => 'rappel_echeance',
            $parametres->bool('notification_retard_actif') && $aujourdhui->gt($echeance) => 'retard',
            default => null,
        };
        if (! $type) {
            $this->info('Aucun rappel à envoyer aujourd\'hui.');

            return self::SUCCESS;
        }

        $cotisations = Cotisation::with('membre.user')->where('periode', (string) $periode)->dues()->get()
            ->filter(fn ($c) => $c->membre->user?->actif && $c->membre->user->preference('recevoir_rappels', true));

        foreach ($cotisations as $c) {
            /** @var User $user */
            $user = $c->membre->user;
            [$titre, $texte] = match ($type) {
                'rappel_echeance' => ['Rappel : cotisation de '.$periode->libelle(),
                    'Votre cotisation de '.fcfa($c->reste).' est attendue au plus tard le '.$echeance->format('d/m/Y').'.'],
                'rappel_jour_echeance' => ['Échéance aujourd\'hui',
                    'Dernier jour pour régler votre cotisation de '.$periode->libelle().' ('.fcfa($c->reste).') sans retard.'],
                'retard' => ['Cotisation en retard',
                    'Votre cotisation de '.$periode->libelle().' ('.fcfa($c->reste).' restant) n\'a pas été réglée à l\'échéance du '.$echeance->format('d/m/Y').'.'],
            };
            $notifications->envoyer($user, new Message($type, $titre, $texte, route('mon-historique'), $type.':'.$periode));
        }

        $this->info($cotisations->count()." rappel(s) « {$type} » traité(s).");

        return self::SUCCESS;
    }
}
