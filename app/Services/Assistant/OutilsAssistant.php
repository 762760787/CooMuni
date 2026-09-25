<?php

namespace App\Services\Assistant;

use App\Models\Cotisation;
use App\Models\Membre;
use App\Models\ModePaiement;
use App\Models\User;
use App\Services\PaiementService;
use App\Services\Parametres;
use App\Services\Statistiques;
use App\Support\Periode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Outils mis à la disposition de l'assistante IA.
 *
 * Principe de sécurité : l'IA ne fait que LIRE et PRÉPARER. Aucun outil n'écrit en
 * caisse ; « proposer_encaissement » dépose une proposition que l'utilisateur doit
 * confirmer, puis le paiement passe par PaiementService (mêmes règles, même audit
 * que la saisie manuelle). Chaque outil revérifie les permissions de l'utilisateur.
 */
class OutilsAssistant
{
    /** Proposition déposée pendant le tour en cours (clé de cache), le cas échéant. */
    public ?string $propositionId = null;

    public function __construct(
        private PaiementService $paiements,
        private Statistiques $stats,
        private Parametres $parametres,
    ) {}

    /** Définitions JSON-Schema (strictes) des outils autorisés pour cet utilisateur. */
    public function definitions(User $user): array
    {
        $outils = [];
        if ($user->can('membres.voir') || $user->can('paiements.creer')) {
            $outils[] = [
                'name' => 'rechercher_membre',
                'description' => 'Recherche des membres de la coopérative par nom, prénom, matricule ou téléphone. '
                    .'Renvoie les correspondances exactes, sinon des correspondances approchantes. À appeler avant toute action sur un membre.',
                'strict' => true,
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['terme' => ['type' => 'string', 'description' => 'Nom et/ou prénom, matricule (ex. NGD-029) ou téléphone']],
                    'required' => ['terme'],
                    'additionalProperties' => false,
                ],
            ];
            $outils[] = [
                'name' => 'situation_membre',
                'description' => 'Situation de cotisation d\'un membre : mois dus (les plus anciens d\'abord), mois payables d\'avance, reste dû, statistiques.',
                'strict' => true,
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['membre_id' => ['type' => 'integer', 'description' => 'Identifiant renvoyé par rechercher_membre']],
                    'required' => ['membre_id'],
                    'additionalProperties' => false,
                ],
            ];
        }
        if ($user->can('paiements.creer')) {
            $outils[] = [
                'name' => 'proposer_encaissement',
                'description' => 'Prépare un encaissement de cotisation et l\'affiche à l\'utilisateur pour CONFIRMATION. '
                    .'N\'enregistre rien : le paiement n\'existe qu\'après le clic « Confirmer » de l\'utilisateur. '
                    .'N\'appeler que lorsque le membre est identifié sans ambiguïté.',
                'strict' => true,
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'membre_id' => ['type' => 'integer'],
                        'periodes' => ['type' => 'array', 'items' => ['type' => 'string', 'description' => 'AAAA-MM'], 'description' => 'Mois réglés, format AAAA-MM'],
                        'montant' => ['type' => 'integer', 'description' => 'Montant reçu en FCFA'],
                        'mode_paiement' => ['type' => 'string', 'description' => 'Code du mode de paiement (liste dans les instructions)'],
                        'date_paiement' => ['type' => 'string', 'description' => 'Date du paiement AAAA-MM-JJ'],
                        'reference' => ['type' => ['string', 'null'], 'description' => 'Référence de transaction (Wave, Orange Money, chèque…) ou null'],
                    ],
                    'required' => ['membre_id', 'periodes', 'montant', 'mode_paiement', 'date_paiement', 'reference'],
                    'additionalProperties' => false,
                ],
            ];
        }
        if ($user->can('impayes.voir')) {
            $outils[] = [
                'name' => 'liste_impayes',
                'description' => 'Liste des membres ayant des cotisations échues non payées (les plus en retard d\'abord), avec le nombre de mois et le montant dû.',
                'strict' => true,
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['limite' => ['type' => 'integer', 'description' => 'Nombre maximum de membres (1 à 30)']],
                    'required' => ['limite'],
                    'additionalProperties' => false,
                ],
            ];
        }
        if ($user->can('tableau_de_bord.global')) {
            $outils[] = [
                'name' => 'situation_du_mois',
                'description' => 'Indicateurs d\'un mois : attendu, encaissé, reste à recouvrer, taux, nombre de membres à jour / en retard / impayés.',
                'strict' => true,
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['periode' => ['type' => 'string', 'description' => 'AAAA-MM']],
                    'required' => ['periode'],
                    'additionalProperties' => false,
                ],
            ];
        }

        return $outils;
    }

    /**
     * Exécute un outil. Retourne [contenu JSON, erreur ?]. Les erreurs sont
     * renvoyées au modèle (is_error) pour qu'il corrige ou interroge l'utilisateur.
     *
     * @return array{0: string, 1: bool}
     */
    public function executer(User $user, string $nom, array $input, string $messageUtilisateur): array
    {
        try {
            $resultat = match ($nom) {
                'rechercher_membre' => $this->rechercherMembre($user, (string) ($input['terme'] ?? '')),
                'situation_membre' => $this->situationMembre($user, (int) ($input['membre_id'] ?? 0)),
                'proposer_encaissement' => $this->proposerEncaissement($user, $input, $messageUtilisateur),
                'situation_du_mois' => $this->situationDuMois($user, (string) ($input['periode'] ?? '')),
                'liste_impayes' => $this->listeImpayes($user, (int) ($input['limite'] ?? 10)),
                default => throw new ErreurOutil("Outil inconnu : {$nom}"),
            };

            return [json_encode($resultat, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), false];
        } catch (ErreurOutil|\App\Exceptions\RegleMetierException $e) {
            return [$e->getMessage(), true];
        }
    }

    private function autoriser(User $user, string ...$permissions): void
    {
        foreach ($permissions as $p) {
            if ($user->can($p)) {
                return;
            }
        }
        throw new ErreurOutil('Action non autorisée pour cet utilisateur.');
    }

    public function rechercherMembre(User $user, string $terme): array
    {
        $this->autoriser($user, 'membres.voir', 'paiements.creer');
        $terme = trim($terme);
        if (mb_strlen($terme) < 2) {
            throw new ErreurOutil('Terme de recherche trop court.');
        }

        $exacts = Membre::recherche($terme)->orderBy('nom')->orderBy('prenom')->limit(10)->get();
        $approchants = collect();
        if ($exacts->isEmpty()) {
            // Aucun membre ne contient tous les mots : on cherche chaque mot séparément.
            $mots = collect(preg_split('/\s+/', $terme))->filter(fn ($m) => mb_strlen($m) >= 2);
            $approchants = Membre::query()
                ->where(function ($q) use ($mots) {
                    foreach ($mots as $mot) {
                        $q->orWhere(fn ($q) => $q->recherche($mot));
                    }
                })
                ->orderBy('nom')->orderBy('prenom')->limit(15)->get();
        }
        $format = fn (Membre $m) => [
            'membre_id' => $m->id,
            'matricule' => $m->matricule,
            'nom_complet' => $m->nom_complet,
            'statut' => $m->statut->label(),
            'telephone' => $m->telephone,
            'service' => $m->service,
        ];

        return [
            'correspondances_exactes' => $exacts->map($format)->values(),
            'correspondances_approchantes' => $approchants->map($format)->values(),
        ];
    }

    public function situationMembre(User $user, int $membreId): array
    {
        $this->autoriser($user, 'membres.voir', 'paiements.creer');
        $membre = Membre::find($membreId) ?? throw new ErreurOutil('Membre introuvable.');
        $s = $this->stats->membre($membre);

        return [
            'membre' => ['membre_id' => $membre->id, 'matricule' => $membre->matricule, 'nom_complet' => $membre->nom_complet, 'statut' => $membre->statut->label()],
            'montant_cotisation_mensuelle' => $this->parametres->montantCotisation(),
            'periodes_reglables' => $this->paiements->periodesReglables($membre),
            'solde_du_echu' => $s['solde_du'],
            'mois_impayes' => $s['impayes'],
            'total_verse' => $s['total_verse'],
            'derniere_periode_payee' => Cotisation::where('membre_id', $membre->id)->whereIn('statut', ['paye', 'paye_retard'])->max('periode'),
        ];
    }

    public function proposerEncaissement(User $user, array $input, string $messageUtilisateur): array
    {
        $this->autoriser($user, 'paiements.creer');
        $membre = Membre::find((int) ($input['membre_id'] ?? 0)) ?? throw new ErreurOutil('Membre introuvable.');
        $mode = ModePaiement::actifs()->where('code', $input['mode_paiement'] ?? '')->first()
            ?? throw new ErreurOutil('Mode de paiement inconnu ou inactif. Codes valides : '.ModePaiement::actifs()->pluck('code')->implode(', '));
        $montant = (int) ($input['montant'] ?? 0);
        $reference = trim((string) ($input['reference'] ?? '')) ?: null;
        $periodes = collect($input['periodes'] ?? [])->filter(fn ($p) => Periode::isValid($p))->unique()->sort()->values();

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', (string) ($input['date_paiement'] ?? ''));
        } catch (\Throwable) {
            $date = false;
        }
        if (! $date) {
            throw new ErreurOutil('Date de paiement invalide (format attendu AAAA-MM-JJ).');
        }
        if ($date->isAfter(today())) {
            throw new ErreurOutil('La date de paiement ne peut pas être dans le futur.');
        }
        if ($periodes->isEmpty()) {
            throw new ErreurOutil('Aucune période valide (format AAAA-MM).');
        }
        if ($mode->reference_requise && ! $reference) {
            throw new ErreurOutil("Le mode « {$mode->nom} » exige une référence de transaction : demande-la à l'utilisateur.");
        }

        $reglables = collect($this->paiements->periodesReglables($membre))->keyBy('periode');
        $inconnues = $periodes->reject(fn ($p) => $reglables->has($p));
        if ($inconnues->isNotEmpty()) {
            throw new ErreurOutil('Période(s) non réglable(s) pour ce membre (déjà payée(s) ou non due(s)) : '.$inconnues->implode(', '));
        }
        $totalDu = $periodes->sum(fn ($p) => $reglables[$p]['reste']);
        if ($montant <= 0 || $montant > $totalDu) {
            throw new ErreurOutil("Montant invalide : il doit être compris entre 1 et {$totalDu} FCFA pour ces périodes.");
        }
        if ($montant < $totalDu && ! $this->parametres->bool('paiement_partiel_autorise')) {
            throw new ErreurOutil("Paiement partiel interdit : le montant doit être de {$totalDu} FCFA.");
        }
        $doublons = $this->paiements->detecterDoublons($membre->id, $montant, $date->toDateString(), $reference);
        if ($doublons['bloquants']) {
            throw new ErreurOutil(implode(' ', $doublons['bloquants']));
        }

        $proposition = [
            'user_id' => $user->id,
            'membre_id' => $membre->id,
            'membre' => $membre->nom_complet,
            'matricule' => $membre->matricule,
            'periodes' => $periodes->all(),
            'periodes_libelle' => $periodes->map(fn ($p) => periode_libelle($p))->implode(', '),
            'montant' => $montant,
            'total_du' => $totalDu,
            'mode_paiement_id' => $mode->id,
            'mode' => $mode->nom,
            'date_paiement' => $date->toDateString(),
            'reference' => $reference,
            'avertissements' => $doublons['avertissements'],
            'message_origine' => Str::limit($messageUtilisateur, 300),
        ];
        $this->propositionId = (string) Str::uuid();
        Cache::put(self::cleCache($this->propositionId), $proposition, now()->addMinutes(30));

        return [
            'statut' => 'en_attente_de_confirmation',
            'resume' => "{$proposition['membre']} ({$proposition['matricule']}) — ".fcfa($montant)
                ." — {$proposition['periodes_libelle']} — {$mode->nom} — ".$date->format('d/m/Y'),
            'paiement_partiel' => $montant < $totalDu,
            'avertissements_doublon' => $doublons['avertissements'],
        ];
    }

    public function situationDuMois(User $user, string $periode): array
    {
        $this->autoriser($user, 'tableau_de_bord.global');
        if (! Periode::isValid($periode)) {
            throw new ErreurOutil('Période invalide (format AAAA-MM).');
        }
        $s = $this->stats->periode(Periode::fromString($periode));
        unset($s['periode']);
        $s['echeance'] = $s['echeance']->toDateString();

        return ['periode' => periode_libelle($periode)] + $s;
    }

    public function listeImpayes(User $user, int $limite): array
    {
        $this->autoriser($user, 'impayes.voir');
        $rows = Cotisation::impayees()
            ->selectRaw('membre_id, COUNT(*) as nb_mois, SUM(montant_attendu - montant_paye) as montant_du, MIN(periode) as depuis')
            ->groupBy('membre_id')->orderByDesc('nb_mois')->orderByDesc('montant_du')
            ->limit(max(1, min(30, $limite)))->get();
        $membres = Membre::whereIn('id', $rows->pluck('membre_id'))->get()->keyBy('id');
        $total = $this->stats->totalImpayes();

        return [
            'total_membres' => $total['membres'],
            'total_montant' => $total['montant'],
            'membres' => $rows->map(fn ($r) => [
                'membre_id' => $r->membre_id,
                'nom_complet' => $membres[$r->membre_id]->nom_complet,
                'matricule' => $membres[$r->membre_id]->matricule,
                'mois_impayes' => (int) $r->nb_mois,
                'montant_du' => (int) $r->montant_du,
                'depuis' => periode_libelle($r->depuis),
            ])->values(),
        ];
    }

    public static function cleCache(string $id): string
    {
        return 'ia.proposition.'.$id;
    }
}
