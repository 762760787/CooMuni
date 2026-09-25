<?php

namespace App\Livewire;

use App\Exceptions\RegleMetierException;
use App\Livewire\Concerns\AvecRetours;
use App\Services\Assistant\AssistantIA;
use App\Services\Assistant\Local\AssistantLocal;
use App\Services\Audit;
use App\Services\Parametres;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Assistante « Fatou » : l'utilisateur écrit ou dicte sa demande en français ou
 * en wolof ; le moteur local (gratuit) la comprend et prépare l'encaissement,
 * que l'utilisateur confirme d'un geste ou de la voix (« oui » / « waaw »).
 * Chaque réponse est aussi lue à voix haute par le navigateur (événement « fatou-parle »).
 * Claude peut être branché en complément.
 */
class Assistant extends Component
{
    use AvecRetours;

    /** @var list<array{role: string, content: string}> */
    #[Locked]
    public array $historique = [];

    /** Mémoire de la demande en cours (membre choisi, question en attente…). */
    #[Locked]
    public array $contexte = [];

    public string $saisie = '';

    /** Proposition en attente : seul l'identifiant circule, les données restent côté serveur. */
    #[Locked]
    public ?string $propositionId = null;

    public bool $confirmerDoublon = false;

    #[Locked]
    public array $confirmes = [];

    public function mount(): void
    {
        $this->exiger('assistant.ia');
    }

    public function envoyer(AssistantLocal $local, AssistantIA $claude, Parametres $parametres): void
    {
        $this->exiger('assistant.ia');
        $this->validate(['saisie' => 'required|string|max:1000'], [], ['saisie' => 'message']);
        if (! $parametres->bool('ia_active')) {
            $this->erreur('L\'assistante est désactivée dans les paramètres.');

            return;
        }
        $message = trim($this->saisie);
        $this->saisie = '';
        $this->historique[] = ['role' => 'user', 'content' => $message];
        $user = auth()->user();

        // Moteur Claude : l'agent IA traite tout, sauf le simple « oui / non » face à une carte (instantané, gratuit).
        if ($claude->estActive() && $claude->moteur() === 'claude') {
            $simple = $this->propositionId ? $local->reponseSimple($message) : null;
            if ($simple === 'oui') {
                $this->confirmer($claude);

                return;
            }
            if ($simple === 'non') {
                $this->abandonner();

                return;
            }
            $ia = $claude->converser($user, array_slice($this->historique, -13, 12), $message);
            $this->contexte = [];
            $this->propositionId = $ia['proposition_id'] ?? $this->propositionId;
            $this->confirmerDoublon = false;
            $this->repondre($ia['reponse'], 'claude');

            return;
        }

        $r = $local->traiter($user, $this->contexte, $message, $this->propositionId !== null);
        $moteur = 'local';

        if ($r['action'] === 'confirmer') {
            $this->confirmer($claude);

            return;
        }
        if (! $r['compris'] && $claude->estActive()) {
            // Mode hybride : ce que le moteur local ne comprend pas est confié à Claude.
            $ia = $claude->converser($user, array_slice($this->historique, -13, 12), $message);
            $r = ['reponse' => $ia['reponse'], 'proposition_id' => $ia['proposition_id'], 'contexte' => [], 'action' => null];
            $moteur = 'claude';
        } else {
            Audit::log('ia.commande', 'Assistant', null, [
                'moteur' => 'local', 'message' => Str::limit($message, 500), 'reponse' => Str::limit($r['reponse'], 500),
                'proposition' => $r['proposition_id'] ? $claude->proposition($user, $r['proposition_id']) : null,
            ], Str::limit($message, 200));
        }

        $this->contexte = $r['contexte'];
        $this->propositionId = $r['proposition_id'];
        $this->confirmerDoublon = false;
        $this->repondre($r['reponse'], $moteur);
    }

    public function confirmer(AssistantIA $ia): void
    {
        $this->exiger('paiements.creer');
        if (! $this->propositionId) {
            $this->repondre('Il n\'y a rien à confirmer pour le moment.');

            return;
        }
        try {
            $paiement = $ia->confirmer(auth()->user(), $this->propositionId, $this->confirmerDoublon);
        } catch (RegleMetierException $e) {
            $this->repondre('Je n\'ai pas pu enregistrer : '.$e->getMessage());

            return;
        }
        $this->propositionId = null;
        $wolof = $this->contexte['wolof'] ?? false;
        $this->contexte = ['wolof' => $wolof];
        $this->confirmes[] = ['id' => $paiement->id, 'numero' => $paiement->numero_recu];
        $this->repondre(($wolof ? "Bind naa ko ✓\n— " : '')."C'est enregistré ✓ Reçu {$paiement->numero_recu} — ".fcfa($paiement->montant).'.');
        $this->succes("Paiement enregistré — reçu {$paiement->numero_recu}.");
    }

    public function abandonner(): void
    {
        $this->propositionId = null;
        $this->contexte = ['wolof' => $this->contexte['wolof'] ?? false];
        $this->repondre('D\'accord, j\'ai annulé cette proposition. Rien n\'a été enregistré.');
    }

    public function recommencer(): void
    {
        $this->reset('historique', 'contexte', 'propositionId', 'confirmes', 'saisie', 'confirmerDoublon');
    }

    /** Ajoute la réponse à la conversation et demande au navigateur de la lire à voix haute. */
    private function repondre(string $texte, string $moteur = 'local'): void
    {
        $this->historique[] = ['role' => 'assistant', 'content' => $texte, 'moteur' => $moteur];
        $this->dispatch('fatou-parle', texte: $texte);
    }

    public function render(AssistantIA $ia, Parametres $parametres)
    {
        return view('livewire.assistant', [
            'nom' => $ia->nom(),
            'active' => $parametres->bool('ia_active'),
            'claude' => $ia->estActive(),
            'moteur' => $parametres->bool('ia_active') ? $ia->moteur() : 'local',
            'moteurDemande' => (string) $parametres->get('ia_moteur', 'local'),
            'proposition' => $ia->proposition(auth()->user(), $this->propositionId),
            'dernierRecu' => collect($this->confirmes)->last(),
        ])->title($ia->nom().' — assistante');
    }
}
