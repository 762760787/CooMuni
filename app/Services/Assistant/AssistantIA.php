<?php

namespace App\Services\Assistant;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Exceptions\RegleMetierException;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Models\User;
use App\Services\Audit;
use App\Services\PaiementService;
use App\Services\Parametres;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Assistante IA « Fatou » : comprend des consignes en français et en wolof,
 * retrouve les membres et prépare les encaissements via des outils (Claude, tool use).
 * L'écriture en caisse n'a lieu qu'après confirmation explicite de l'utilisateur.
 */
class AssistantIA
{
    /** Nombre maximum d'allers-retours outil par message (garde-fou coût / boucle). */
    private const MAX_TOURS = 8;

    public function __construct(
        private OutilsAssistant $outils,
        private Parametres $parametres,
        private PaiementService $paiements,
    ) {}

    public function nom(): string
    {
        return (string) $this->parametres->get('ia_nom', 'Fatou');
    }

    public function estConfiguree(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * Moteur effectivement utilisé : « claude » (agent IA qui raisonne), « hybride »
     * (local d'abord, Claude pour ce que le local ne comprend pas) ou « local » (gratuit).
     * Sans clé d'API, c'est toujours le moteur local.
     */
    public function moteur(): string
    {
        $choix = (string) $this->parametres->get('ia_moteur', 'local');

        return $this->estConfiguree() && in_array($choix, ['claude', 'hybride'], true) ? $choix : 'local';
    }

    /** Claude est-il utilisable (clé fournie, assistante active, moteur Claude ou hybride choisi) ? */
    public function estActive(): bool
    {
        return $this->parametres->bool('ia_active') && $this->moteur() !== 'local';
    }

    protected function client(): Client
    {
        return new Client(apiKey: (string) config('services.anthropic.key'));
    }

    /**
     * Traite un message de l'utilisateur.
     *
     * @param  list<array{role: string, content: string}>  $historique  échanges précédents (texte seul)
     * @return array{reponse: string, proposition_id: ?string}
     */
    public function converser(User $user, array $historique, string $message): array
    {
        $this->outils->propositionId = null;
        $contexte = sprintf('[Contexte automatique — date du jour : %s (%s) ; utilisateur connecté : %s, rôle %s]',
            now()->translatedFormat('l j F Y'), now()->toDateString(), $user->name, $user->roleNom());

        $messages = array_map(fn ($h) => ['role' => $h['role'], 'content' => $h['content']], $historique);
        $messages[] = ['role' => 'user', 'content' => $contexte."\n\n".$message];
        $outils = $this->outils->definitions($user);

        try {
            for ($tour = 0; $tour < self::MAX_TOURS; $tour++) {
                $reponse = $this->client()->beta->messages->create(
                    model: (string) config('services.anthropic.model'),
                    maxTokens: 16000,
                    system: [['type' => 'text', 'text' => $this->instructions(), 'cacheControl' => ['type' => 'ephemeral']]],
                    tools: $outils,
                    messages: $messages,
                    thinking: ['type' => 'adaptive'],
                    outputConfig: ['effort' => (string) config('services.anthropic.effort', 'medium')],
                    // Repli automatique vers un autre modèle si la requête est déclinée.
                    fallbacks: 'default',
                    betas: ['server-side-fallback-2026-07-01'],
                );

                if ($reponse->stopReason === 'refusal') {
                    return $this->terminer($user, $message, 'Je ne peux pas traiter cette demande. Utilisez les écrans habituels de l\'application.');
                }
                if ($reponse->stopReason !== 'tool_use') {
                    return $this->terminer($user, $message, $this->texte($reponse->content)
                        ?: ($reponse->stopReason === 'max_tokens' ? 'Réponse interrompue, pouvez-vous reformuler plus simplement ?' : 'Je n\'ai pas compris, pouvez-vous reformuler ?'));
                }

                $resultats = [];
                foreach ($reponse->content as $bloc) {
                    if ($bloc->type === 'tool_use') {
                        [$contenu, $erreur] = $this->outils->executer($user, $bloc->name, (array) $bloc->input, $message);
                        $resultats[] = ['type' => 'tool_result', 'toolUseID' => $bloc->id, 'content' => $contenu, 'isError' => $erreur];
                    }
                }
                $messages[] = ['role' => 'assistant', 'content' => $reponse->content];
                $messages[] = ['role' => 'user', 'content' => $resultats];
            }

            return $this->terminer($user, $message, 'La demande est trop complexe pour moi. Pouvez-vous la découper en étapes plus simples ?');
        } catch (AuthenticationException) {
            return ['reponse' => 'La clé d\'accès à l\'IA est invalide. Un administrateur doit vérifier ANTHROPIC_API_KEY.', 'proposition_id' => null];
        } catch (RateLimitException) {
            return ['reponse' => 'Le service d\'IA est momentanément saturé. Réessayez dans une minute.', 'proposition_id' => null];
        } catch (APIConnectionException) {
            return ['reponse' => 'Impossible de joindre le service d\'IA (connexion Internet ?). Vous pouvez saisir le paiement manuellement.', 'proposition_id' => null];
        } catch (APIStatusException $e) {
            report($e);

            return ['reponse' => 'Le service d\'IA a rencontré une erreur. Réessayez ou saisissez le paiement manuellement.', 'proposition_id' => null];
        }
    }

    private function terminer(User $user, string $message, string $reponse): array
    {
        Audit::log('ia.commande', 'Assistant', null, [
            'message' => Str::limit($message, 500),
            'reponse' => Str::limit($reponse, 500),
            'proposition' => $this->outils->propositionId ? Cache::get(OutilsAssistant::cleCache($this->outils->propositionId)) : null,
        ], Str::limit($message, 200), $user->id);

        return ['reponse' => $reponse, 'proposition_id' => $this->outils->propositionId];
    }

    private function texte(array $contenu): string
    {
        return trim(implode("\n", array_map(fn ($b) => $b->text, array_filter($contenu, fn ($b) => $b->type === 'text'))));
    }

    public function proposition(User $user, ?string $id): ?array
    {
        if (! $id) {
            return null;
        }
        $p = Cache::get(OutilsAssistant::cleCache($id));

        return $p && $p['user_id'] === $user->id ? $p : null;
    }

    /** Enregistre l'encaissement préparé par l'IA, après confirmation de l'utilisateur. */
    public function confirmer(User $user, string $id, bool $confirmerDoublon): Paiement
    {
        abort_unless($user->can('paiements.creer'), 403);
        $p = $this->proposition($user, $id) ?? throw new RegleMetierException('Cette proposition a expiré. Redemandez l\'encaissement à '.$this->nom().'.');
        if ($p['avertissements'] && ! $confirmerDoublon) {
            throw new RegleMetierException('Doublon possible : cochez la confirmation avant de valider.');
        }

        $paiement = $this->paiements->enregistrer([
            'membre_id' => $p['membre_id'],
            'periodes' => $p['periodes'],
            'montant' => $p['montant'],
            'date_paiement' => $p['date_paiement'],
            'mode_paiement_id' => $p['mode_paiement_id'],
            'reference' => $p['reference'],
            'note' => 'Saisi via l\'assistante '.$this->nom().' : « '.$p['message_origine'].' »',
            'confirmer_doublon' => $confirmerDoublon,
        ]);
        Cache::forget(OutilsAssistant::cleCache($id));

        return $paiement;
    }

    /** Instructions système (stables d'un appel à l'autre, donc mises en cache). */
    public function instructions(): string
    {
        $nom = $this->nom();
        $montant = fcfa($this->parametres->montantCotisation());
        $echeance = $this->parametres->jourEcheance();
        $modes = ModePaiement::actifs()->get()
            ->map(fn ($m) => "- {$m->code} : {$m->nom}".($m->reference_requise ? ' (référence de transaction obligatoire)' : ''))
            ->implode("\n");

        return <<<TXT
Tu es {$nom}, l'assistante de la coopérative du personnel de la Commune de Ngoundiane (Sénégal). Le trésorier et les responsables te parlent comme à une collègue, en français, en wolof ou en mélangeant les deux, souvent à la voix, pour encaisser des cotisations et suivre la caisse sans remplir de formulaire. Ton rôle : comprendre ce qu'ils veulent vraiment, faire tout le travail de recherche et de préparation, et ne les solliciter que lorsque c'est indispensable.

Ton nom est {$nom}. « {$nom}, … » t'interpelle. Des membres peuvent aussi s'appeler {$nom} : distingue ton nom de celui d'un membre d'après le sens de la phrase.

## Règles de la coopérative
- Cotisation mensuelle : {$montant} par membre ; échéance le {$echeance} de chaque mois. Payé au plus tard le {$echeance} = à temps ; après = en retard ; non réglé après l'échéance = impayé.
- Un paiement peut couvrir plusieurs mois (dans l'ordre, du plus ancien au plus récent), une partie de mois, ou des mois d'avance.

## Comment tu travailles
Réfléchis avant d'agir et appuie-toi toujours sur les outils : ils donnent les vraies données. N'invente jamais un membre, un montant ou un mois.
- Retrouve le membre avec rechercher_membre, puis lis sa situation avec situation_membre avant de proposer quoi que ce soit.
- Décide toi-même des valeurs raisonnables quand l'utilisateur ne les précise pas : les mois dus les plus anciens (ou le prochain mois si le membre est à jour), le reste dû comme montant, les espèces comme mode, la date du jour. Un montant donné sans mois couvre les mois dus les plus anciens ; un nombre de mois (« 2 mois ») prend les plus anciens mois dus. Mentionne brièvement tes choix dans la réponse.
- Ne pose une question que si l'information manque vraiment ou est ambiguë : plusieurs membres possibles (homonymes — liste-les avec leur matricule), nom introuvable, référence de transaction exigée pour Wave, Orange Money, virement ou chèque, montant incohérent.
- Quand tout est clair, appelle proposer_encaissement. Cet outil n'enregistre rien : il affiche une carte que l'utilisateur confirme en appuyant sur « Confirmer » ou en disant « oui ». Ne dis donc jamais que le paiement est enregistré ; dis qu'il est prêt à confirmer.
- Si l'utilisateur corrige (« non, plutôt 2 mois », « c'était par Wave »), refais simplement une proposition corrigée.
- Pour une question (« combien doit… ? », « qui n'a pas payé ? », « bilan du mois »), réponds avec situation_membre, liste_impayes ou situation_du_mois, sans rien proposer d'office.
- Hors de ton périmètre (annulation d'un paiement, opération de caisse, modification d'un membre), indique l'écran de l'application à utiliser.

## Modes de paiement (utilise le code)
{$modes}

## Comprendre le wolof
- Temps : tey / tay = aujourd'hui ; démb = hier ; bërki-démb = avant-hier ; leegi = maintenant ; weer wii = ce mois ; weer wi weesu = le mois dernier ; weer wi ñëw = le mois prochain.
- Jours : altine lundi, talaata mardi, àllarba mercredi, alxames jeudi, àjjuma vendredi, gaawu samedi, dibéer dimanche.
- Payer : fey, jox (xaalis), indi (apporter), yónnee (envoyer, par Wave ou OM) ; dafa fey / fey na = il/elle a payé ; feyagul / feyul = n'a pas (encore) payé ; bor = dette ; lépp / yépp = tout ; bindal / dugal = enregistre.
- Questions : naata = combien ; kan = qui ; ndax = est-ce que ; ana = où.
- Oui / non : waaw / déedéet.
- Nombres : benn 1, ñaar 2, ñett 3, ñeent 4, juróom 5, juróom-benn 6, fukk 10, téeméer 100, junni 1 000 ; forme liée : ñaari, ñetti, juróomi, fukki (« ñaari weer » = 2 mois).
- Argent : à l'oral, les montants se comptent souvent en dërëm (1 dërëm = 5 FCFA). « junni » = 1 000 dërëm = 5 000 FCFA ; « ñaari junni » = 10 000 FCFA ; « fukki junni » = 50 000 FCFA. Choisis l'interprétation cohérente avec une cotisation de {$montant} et redonne toujours le montant en FCFA.
- Modes : cash, liquide, loxo = espèces ; Wave ; OM = Orange Money.
- Noms : les orthographes wolof et françaises alternent (Njaay = Ndiaye, Juuf = Diouf, Joob = Diop, Seen = Sène, Useynu = Ousseynou, Faatu = Fatou). Essaie les deux avec rechercher_membre.
- Dictée vocale : les messages dictés passent par une reconnaissance vocale réglée en français. Les mots wolof et les noms peuvent être mal transcrits (« tey » → « thé », « fey » → « fait », « dafa » → « d'après », « Ngom » → « nom »). Interprète d'après le son et le contexte ; si un nom reste incertain, cherche les variantes plausibles et demande confirmation.

## Exemples
Utilisateur : « Amy Tine dafa fey ñaari weer tey ci Wave »
→ rechercher_membre(« Amy Tine »), situation_membre, puis, faute de référence Wave : « Amy TINE dafa fey ñaari weer (août, septembre = 20 000 FCFA). Référence Wave bi, lan la ?\n— Pour Wave, il me faut la référence de la transaction : quelle est-elle ? »
Utilisateur : « Daouda Sene 10000 »
→ rechercher_membre trouve trois Daouda SENE : « Il y a trois Daouda SENE : NGD-020, NGD-068 et NGD-089. Lequel ? »
Utilisateur : « Combien doit Marie Faye ? »
→ situation_membre : « Marie FAYE doit 20 000 FCFA (août et septembre). Voulez-vous que je prépare l'encaissement ? »

## Style de réponse
- Très court (1 à 3 phrases) : tes réponses sont souvent lues à voix haute. Pas de listes à puces sauf pour des candidats ou des impayés ; montants en FCFA ; dates au format JJ/MM/AAAA.
- Si l'utilisateur parle wolof : réponds d'abord en wolof simple, puis ajoute une ligne commençant par « — » avec la version française (c'est cette ligne qui est lue à voix haute). Sinon, réponds uniquement en français.
TXT;
    }
}
