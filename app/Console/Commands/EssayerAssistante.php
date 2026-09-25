<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Assistant\AssistantIA;
use App\Services\Assistant\Local\AssistantLocal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Essai du moteur local de l'assistante sur la base réelle, sans rien enregistrer :
 *   php artisan coop:fatou "Amy Tine dafa fey tey"
 *   php artisan coop:fatou --fichier=phrases.txt     (une phrase par ligne)
 * Utile pour vérifier une formulation puis enrichir le vocabulaire (app/Services/Assistant/Local/Lexique.php).
 */
class EssayerAssistante extends Command
{
    protected $signature = 'coop:fatou {phrase?* : Phrase(s) à tester} {--fichier= : Fichier texte, une phrase par ligne} {--utilisateur=tresorier}';

    protected $description = 'Teste la compréhension de l\'assistante (moteur local) sans rien enregistrer.';

    public function handle(AssistantLocal $local, AssistantIA $ia): int
    {
        $user = User::where('identifiant', $this->option('utilisateur'))->first();
        if (! $user) {
            $this->error('Utilisateur introuvable.');

            return self::FAILURE;
        }
        Auth::setUser($user);

        $phrases = $this->argument('phrase');
        if ($f = $this->option('fichier')) {
            $phrases = array_merge($phrases, array_filter(array_map('trim', file($f) ?: [])));
        }
        if (! $phrases) {
            $this->warn('Aucune phrase. Exemple : php artisan coop:fatou "Encaisse Amy Tine 10000 cash"');

            return self::SUCCESS;
        }

        foreach ($phrases as $phrase) {
            $r = $local->traiter($user, [], $phrase);
            $p = $ia->proposition($user, $r['proposition_id']);
            $this->line("<fg=cyan>» {$phrase}</>");
            $this->line($p
                ? "  <fg=green>[CARTE]</> {$p['membre']} | ".implode(', ', $p['periodes'])." | ".fcfa($p['montant'])." | {$p['mode']} | {$p['date_paiement']}".($p['reference'] ? " | réf. {$p['reference']}" : '')
                : '  '.str_replace("\n", "\n  ", $r['reponse']).($r['compris'] ? '' : '  <fg=yellow>(non compris)</>'));
            if ($r['proposition_id']) {
                Cache::forget('ia.proposition.'.$r['proposition_id']);
            }
        }

        return self::SUCCESS;
    }
}
