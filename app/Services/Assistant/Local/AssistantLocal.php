<?php

namespace App\Services\Assistant\Local;

use App\Exceptions\RegleMetierException;
use App\Models\Cotisation;
use App\Models\Membre;
use App\Models\ModePaiement;
use App\Models\User;
use App\Services\Assistant\ErreurOutil;
use App\Services\Assistant\OutilsAssistant;
use App\Services\PaiementService;
use App\Services\Parametres;
use App\Services\Statistiques;
use App\Support\Periode;
use Carbon\CarbonImmutable;

/**
 * Moteur de dialogue local de l'assistante (gratuit, sans service externe).
 *
 * Le contexte de conversation (tableau sérialisable) mémorise la demande en
 * cours : membre choisi, champs donnés par l'utilisateur, question en attente.
 */
class AssistantLocal
{
    private bool $wolof = false;

    public function __construct(
        private OutilsAssistant $outils,
        private PaiementService $paiements,
        private Statistiques $stats,
        private Parametres $parametres,
    ) {}

    /**
     * @return array{reponse: string, proposition_id: ?string, contexte: array, action: ?string, compris: bool}
     */
    public function traiter(User $user, array $contexte, string $message, bool $propositionEnAttente = false): array
    {
        $a = $this->analyseur()->analyser($message);
        // Une réponse courte (« 2 », « waaw ») garde la langue de l'échange en cours.
        $this->wolof = $a->wolof || (($contexte['wolof'] ?? false) && count(Texte::mots(Texte::normaliser($message))) <= 3);
        $contexte['wolof'] = $this->wolof;
        $resolveur = new ResolveurMembre((string) $this->parametres->get('matricule_prefixe', ''));

        // 1. Une proposition attend : « oui » confirme, « non » abandonne, une précision la modifie.
        if ($propositionEnAttente) {
            if ($a->oui && ! $a->aDesChamps() && ! $a->non) {
                return $this->resultat('', $contexte, action: 'confirmer');
            }
            if ($a->non && ! $a->aDesChamps()) {
                return $this->resultat($this->dire('D\'accord, j\'annule. Rien n\'a été enregistré.', 'Baax na, bàyyi naa ko. Dara bindu wul.'),
                    ['wolof' => $contexte['wolof']], action: 'abandonner');
            }
        }

        // 2. Réponse à « lequel ? » (homonymes).
        if (($contexte['attente'] ?? null) === 'membre' && ! empty($contexte['candidats'])) {
            $choisi = $this->choisirCandidat($a, $contexte['candidats'], $resolveur);
            if ($choisi) {
                $contexte['champs'] = array_merge($contexte['champs'] ?? [], $a->champs(), ['membre_id' => $choisi->id]);
                unset($contexte['attente'], $contexte['candidats']);

                return $this->executer($user, $contexte, $message);
            }
            if ($a->non) {
                return $this->resultat($this->dire('D\'accord, j\'abandonne cette demande.', 'Baax na, bàyyi naa ko.'), ['wolof' => $contexte['wolof']]);
            }
        }

        // 3. Réponse à « quelle est la référence ? ».
        if (($contexte['attente'] ?? null) === 'reference') {
            $reference = $a->reference ?? $this->referenceBrute($message);
            if ($reference) {
                $contexte['champs']['reference'] = $reference;
                unset($contexte['attente']);

                return $this->executer($user, $contexte, $message);
            }
            if ($a->mode) {
                $contexte['champs']['mode'] = $a->mode;
                unset($contexte['attente']);

                return $this->executer($user, $contexte, $message);
            }
        }

        // 4. « Oui » après une situation : encaisser tous les mois dus.
        if (($contexte['attente'] ?? null) === 'oui_encaisser' && ($a->oui || $a->paiement) && ! $a->non) {
            $contexte['intention'] = 'encaisser';
            $contexte['champs'] = array_merge(['tout' => true], $contexte['champs'] ?? [], $a->champs());
            unset($contexte['attente']);

            return $this->executer($user, $contexte, $message);
        }

        // 5. Recherche du membre.
        $r = $resolveur->resoudre($a);

        // 6. Précision apportée à la demande en cours (« plutôt 2 mois », « par Wave réf 123 ») :
        //    les mots non reconnus restants sont alors du bruit, pas un nouveau membre.
        if (in_array($r['statut'], [ResolveurMembre::AUCUN_NOM, ResolveurMembre::INTROUVABLE], true)
            && ! empty($contexte['champs']['membre_id']) && $a->aDesChamps()
            && ($contexte['intention'] ?? null) === 'encaisser') {
            $contexte['champs'] = $this->fusionner($contexte['champs'], $a->champs());
            unset($contexte['attente']);

            return $this->executer($user, $contexte, $message);
        }

        // 7. Demandes générales sans membre.
        // (« Encaisse Xyz » avec un nom inconnu n'est pas une question générale.)
        if ($r['statut'] === ResolveurMembre::AUCUN_NOM || ($r['statut'] === ResolveurMembre::INTROUVABLE && ! $a->paiement)) {
            if ($a->impayes && $user->can('impayes.voir')) {
                return $this->resultat($this->listeImpayes(), ['wolof' => $contexte['wolof']]);
            }
            if (($a->bilan || ($a->situation && ! $a->motsNom)) && $user->can('tableau_de_bord.global')) {
                return $this->resultat($this->bilan($a), ['wolof' => $contexte['wolof']]);
            }
        }

        if ($r['statut'] === ResolveurMembre::AUCUN_NOM) {
            if ($a->merci) {
                return $this->resultat($this->dire('Avec plaisir !', 'Ñoo ko bokk !'), $contexte);
            }
            if ($a->salutation || $a->aide || ! $a->aDesChamps()) {
                return $this->resultat($this->aide($a->salutation), $contexte, compris: $a->salutation || $a->aide);
            }

            return $this->resultat($this->dire(
                'Pour quel membre ? Donnez son prénom et son nom (ou son matricule).',
                'Ngir kan ? Joxma turam ak santaam (walla matricule bi).',
            ), array_merge($contexte, ['intention' => 'encaisser', 'champs' => $a->champs(), 'attente' => null]));
        }

        if ($r['statut'] === ResolveurMembre::INTROUVABLE) {
            return $this->resultat($this->dire(
                'Je ne trouve aucun membre correspondant à « '.implode(' ', $a->motsNom).' ». Vérifiez l\'orthographe ou donnez le matricule (ex. NGD-029).',
                'Gisuma kenn ku tudd « '.implode(' ', $a->motsNom).' ». Seetal bind bi walla joxma matricule bi.',
            ), ['wolof' => $contexte['wolof']], compris: false);
        }

        $intention = $this->intention($a);
        if ($r['statut'] === ResolveurMembre::AMBIGU) {
            $liste = collect($r['membres'])->values()->map(fn (Membre $m, $i) => ($i + 1).') '.$m->nom_complet.' — '.$m->matricule.($m->service ? ', '.$m->service : ''))->implode("\n");

            // Homonymes (même nom complet) ou plusieurs personnes citées dans la même phrase ?
            $homonymes = collect($r['membres'])->map(fn (Membre $m) => Texte::normaliser($m->nom_complet))->unique()->count() === 1;

            return $this->resultat(
                ($homonymes
                    ? $this->titre('Plusieurs membres portent ce nom :', 'Ñu bari ñoo am tur boobu :')
                    : $this->titre('Plusieurs personnes correspondent (un encaissement à la fois) :', 'Ñu bari la wax (benn benn) :'))
                ."\n{$liste}\n".$this->titre('Lequel ? Répondez par le numéro (1, 2…) ou le matricule.', 'Kan la ? Tontul ak nimero bi walla matricule bi.'),
                [
                'wolof' => $contexte['wolof'], 'intention' => $intention, 'champs' => $a->champs(),
                'attente' => 'membre', 'candidats' => collect($r['membres'])->pluck('id')->all(),
            ]);
        }

        $contexte = ['wolof' => $contexte['wolof'], 'intention' => $intention, 'champs' => $a->champs() + ['membre_id' => $r['membres'][0]->id]];

        return $this->executer($user, $contexte, $message);
    }

    /** « oui » / « non » simples (sans autre précision) : confirmer ou abandonner une proposition. */
    public function reponseSimple(string $message): ?string
    {
        $a = $this->analyseur()->analyser($message);
        if ($a->aDesChamps() || $a->motsNom) {
            return null;
        }

        return match (true) {
            $a->oui && ! $a->non => 'oui',
            $a->non && ! $a->oui => 'non',
            default => null,
        };
    }

    private function analyseur(): Analyseur
    {
        return new Analyseur(
            (string) $this->parametres->get('ia_nom', 'Fatou'),
            (string) $this->parametres->get('matricule_prefixe', 'NGD-'),
            ModePaiement::actifs()->pluck('nom', 'code')->all(),
        );
    }

    private function intention(Analyse $a): string
    {
        $precisions = $a->montant !== null || $a->mode !== null || $a->reference !== null || $a->nbMois !== null || $a->tout;
        if ($a->question && ! $precisions) {
            return 'situation';
        }
        if ($a->paiement || $precisions || ($a->periodes || $a->moisSansAnnee) && ! $a->situation) {
            return 'encaisser';
        }

        return 'situation';
    }

    private function executer(User $user, array $contexte, string $message): array
    {
        $membre = Membre::find($contexte['champs']['membre_id'] ?? 0);
        if (! $membre) {
            return $this->resultat($this->dire('Membre introuvable.', 'Gisuma ku mel noonu.'), ['wolof' => $contexte['wolof']]);
        }

        return ($contexte['intention'] ?? 'situation') === 'encaisser' && $user->can('paiements.creer')
            ? $this->encaisser($user, $membre, $contexte, $message)
            : $this->situation($membre, $contexte);
    }

    private function situation(Membre $membre, array $contexte): array
    {
        $dues = Cotisation::where('membre_id', $membre->id)->dues()->orderBy('periode')->get();
        $echues = $dues->filter(fn ($c) => $c->estEchue());
        $dernier = Cotisation::where('membre_id', $membre->id)->whereIn('statut', ['paye', 'paye_retard'])->max('periode');
        $nom = "{$membre->nom_complet} ({$membre->matricule})";

        if ($dues->isEmpty()) {
            return $this->resultat($this->dire(
                "{$nom} est à jour ✓".($dernier ? ' Dernier mois payé : '.periode_libelle($dernier).'.' : ''),
                "{$nom} fey na lépp ✓".($dernier ? ' Weer wu mujj : '.periode_libelle($dernier).'.' : ''),
            ), ['wolof' => $contexte['wolof'], 'intention' => 'encaisser', 'champs' => ['membre_id' => $membre->id]]);
        }

        $liste = $dues->map(fn ($c) => $c->periode_libelle.($c->montant_paye ? ' (reste '.fcfa($c->reste).')' : ''))->implode(', ');
        $total = fcfa($dues->sum('reste'));
        $retard = $echues->count() ? ' dont '.$echues->count().' en retard' : '';

        return $this->resultat($this->dire(
            "{$nom} doit {$total} : {$liste}{$retard}.\nRépondez « oui » pour tout encaisser, ou précisez (ex. « 1 mois », « 5000 par Wave »).",
            "{$nom} war na fey {$total} : {$liste}.\nWax « waaw » ngir fey lépp, walla wax li mu fey (misaal « benn weer », « 5000 ci Wave »).",
        ), ['wolof' => $contexte['wolof'], 'intention' => 'encaisser', 'champs' => ['membre_id' => $membre->id], 'attente' => 'oui_encaisser']);
    }

    private function encaisser(User $user, Membre $membre, array $contexte, string $message): array
    {
        $champs = $contexte['champs'];
        $reglables = collect($this->paiements->periodesReglables($membre))->values();
        if ($reglables->isEmpty()) {
            return $this->resultat($this->dire("{$membre->nom_complet} n'a aucun mois à régler.", "{$membre->nom_complet} amul benn weer bu mu war fey."), ['wolof' => $contexte['wolof']]);
        }
        $dues = $reglables->where('existe', true)->values();
        $note = '';

        // Choix des mois.
        if (! empty($champs['periodes']) || ! empty($champs['moisSansAnnee'])) {
            $periodes = collect($champs['periodes'] ?? []);
            foreach ($champs['moisSansAnnee'] ?? [] as $mois) {
                $periodes->push($this->periodeProbable((int) $mois));
            }
            $periodes = $periodes->unique()->sort()->values();

            // C. Mois demandés déjà réglés ou non dus : explication claire et suggestion.
            $nonReglables = $periodes->reject(fn ($p) => $reglables->contains('periode', $p));
            if ($nonReglables->isNotEmpty()) {
                $payes = Cotisation::where('membre_id', $membre->id)->whereIn('periode', $nonReglables)->whereIn('statut', ['paye', 'paye_retard', 'regularise'])->pluck('periode');
                $libelles = $nonReglables->map(fn ($p) => periode_libelle($p))->implode(', ');
                $suivant = $reglables->first();
                $contexte['attente'] = $suivant ? 'oui_encaisser' : null;
                $contexte['champs'] = ['membre_id' => $membre->id];
                $dejaPaye = $payes->count() === $nonReglables->count();
                $fr = $dejaPaye ? "{$membre->nom_complet} a déjà payé {$libelles}." : "{$libelles} : pas de cotisation à régler pour {$membre->nom_complet}.";
                $wo = $dejaPaye ? "{$membre->nom_complet} fey na ba noppi {$libelles}." : "{$libelles} : {$membre->nom_complet} warul fey dara.";
                if ($suivant) {
                    $fr .= " Prochain mois à régler : {$suivant['libelle']} — répondez « oui » pour l'encaisser.";
                    $wo .= " Weer wi topp : {$suivant['libelle']} — wax « waaw » ngir fey ko.";
                }

                return $this->resultat($this->dire($fr, $wo), $contexte);
            }
        } elseif (! empty($champs['tout'])) {
            $periodes = $dues->pluck('periode');
            if ($periodes->isEmpty()) {
                $periodes = collect([$reglables->first()['periode']]);
                $note = $this->dire(' (Membre à jour : paiement d\'avance.)', ' (Fey na lépp : lii mooy avance.)');
            }
        } elseif (! empty($champs['nbMois'])) {
            $periodes = $reglables->take((int) $champs['nbMois'])->pluck('periode');
        } elseif (! empty($champs['montant'])) {
            $periodes = collect();
            $cumul = 0;
            foreach ($reglables as $r) {
                if ($cumul >= $champs['montant']) {
                    break;
                }
                $periodes->push($r['periode']);
                $cumul += $r['reste'];
            }
        } else {
            $periodes = collect([$reglables->first()['periode']]);
            if ($dues->isEmpty()) {
                $note = $this->dire(' (Membre à jour : paiement d\'avance.)', ' (Fey na lépp : lii mooy avance.)');
            }
        }

        $reste = $periodes->sum(fn ($p) => $reglables->firstWhere('periode', $p)['reste'] ?? 0);
        if ($note === '' && $periodes->every(fn ($p) => ! ($reglables->firstWhere('periode', $p)['existe'] ?? true))) {
            $note = $this->dire(' (Paiement d\'avance.)', ' (Avance la.)');
        }
        $mode = $champs['mode'] ?? (ModePaiement::actifs()->where('code', 'especes')->value('code') ?? ModePaiement::actifs()->value('code'));
        $modeModele = ModePaiement::where('code', $mode)->first();

        if ($modeModele?->reference_requise && empty($champs['reference'])) {
            $contexte['attente'] = 'reference';

            return $this->resultat($this->dire(
                "Pour un paiement {$modeModele->nom}, j'ai besoin de la référence de la transaction (le code reçu par SMS). Quelle est-elle ?",
                "Ngir {$modeModele->nom}, soxla naa référence bi (code bi ñu la yónnee ci SMS). Lan la ?",
            ), $contexte);
        }

        try {
            $this->outils->propositionId = null;
            $this->outils->proposerEncaissement($user, [
                'membre_id' => $membre->id,
                'periodes' => $periodes->all(),
                'montant' => (int) ($champs['montant'] ?? $reste),
                'mode_paiement' => $mode,
                'date_paiement' => $champs['date'] ?? today()->toDateString(),
                'reference' => $champs['reference'] ?? null,
            ], $message);
        } catch (ErreurOutil|RegleMetierException $e) {
            $contexte['attente'] = null;

            return $this->resultat($this->dire('Je ne peux pas préparer ce paiement : '.$e->getMessage(),
                'Mënuma ko def : '.$e->getMessage()), $contexte);
        }

        $montant = fcfa((int) ($champs['montant'] ?? $reste));
        $mois = $periodes->map(fn ($p) => periode_libelle($p))->implode(', ');
        $date = CarbonImmutable::parse($champs['date'] ?? today())->format('d/m/Y');
        $modeNom = $modeModele?->nom ?? $mode;
        $contexte['attente'] = null;

        return $this->resultat($this->dire(
            "C'est prêt : {$montant} pour {$membre->nom_complet} ({$mois}), {$modeNom}, le {$date}.{$note}\nVérifiez puis appuyez sur « Confirmer » (ou répondez « oui »).",
            "Pare na : {$montant} ngir {$membre->nom_complet} ({$mois}), {$modeNom}, {$date}.{$note}\nSeetal te bësal « Confirmer » (walla wax « waaw »).",
        ), $contexte, $this->outils->propositionId);
    }

    /**
     * Mois cité sans année : l'occurrence la plus proche d'aujourd'hui — jusqu'à 3 mois
     * à l'avance (paiement anticipé), sinon dans le passé (arriérés).
     */
    private function periodeProbable(int $mois): string
    {
        $courante = Periode::courante();
        $ecart = ($mois - $courante->mois + 12) % 12;

        return (string) $courante->ajouterMois($ecart <= 3 ? $ecart : $ecart - 12);
    }

    private function fusionner(array $anciens, array $nouveaux): array
    {
        // Une nouvelle façon de désigner les mois remplace l'ancienne ; le montant n'est alors gardé que s'il est redonné.
        if (array_intersect_key($nouveaux, array_flip(['periodes', 'moisSansAnnee', 'nbMois', 'tout']))) {
            unset($anciens['periodes'], $anciens['moisSansAnnee'], $anciens['nbMois'], $anciens['tout'], $anciens['montant']);
        }

        return array_merge($anciens, $nouveaux);
    }

    private function choisirCandidat(Analyse $a, array $candidats, ResolveurMembre $resolveur): ?Membre
    {
        $brut = Texte::normaliser($a->texte);
        $n = null;
        if (preg_match('/^(?:le\s+|la\s+|numero\s+|no\s+|n\s+)?(\d{1,2})(?:er|e|eme)?\s*$/', $brut, $m)) {
            $n = (int) $m[1];
        }
        $n ??= $a->ordinal ?? (count($a->petitsNombres) === 1 && $a->petitsNombres[0] <= count($candidats) ? $a->petitsNombres[0] : null);
        if ($n !== null && isset($candidats[$n - 1]) && ! preg_match('/\d{3}/', $brut)) {
            return Membre::find($candidats[$n - 1]);
        }
        // Matricule ou nom plus précis, parmi les candidats.
        if ($a->matricule === null && count($a->petitsNombres) === 1) {
            $a->matricule = (string) $a->petitsNombres[0];
        }
        $r = $resolveur->resoudre($a, $candidats);
        if ($r['statut'] === ResolveurMembre::UNIQUE) {
            return $r['membres'][0];
        }
        // Précision par le service (« celui de l'état civil »).
        $mots = Texte::mots($brut);
        $parService = Membre::whereIn('id', $candidats)->get()->filter(function (Membre $m) use ($mots) {
            $service = Texte::mots(Texte::normaliser((string) $m->service));

            return $service && array_intersect(array_filter($service, fn ($x) => strlen($x) > 3), $mots);
        });

        return $parService->count() === 1 ? $parService->first() : null;
    }

    private function referenceBrute(string $message): ?string
    {
        $t = trim($message);
        if (preg_match('/^[A-Za-z0-9\-_.]{3,40}$/', $t) && preg_match('/\d/', $t)) {
            return strtoupper($t);
        }

        return null;
    }

    private function listeImpayes(): string
    {
        $rows = Cotisation::impayees()->selectRaw('membre_id, COUNT(*) as n, SUM(montant_attendu - montant_paye) as du')
            ->groupBy('membre_id')->orderByDesc('n')->orderByDesc('du')->limit(10)->get();
        if ($rows->isEmpty()) {
            return $this->dire('Aucun impayé : tous les membres sont à jour ✓', 'Kenn amul bor ✓');
        }
        $membres = Membre::whereIn('id', $rows->pluck('membre_id'))->get()->keyBy('id');
        $t = $this->stats->totalImpayes();
        $liste = $rows->map(fn ($r) => '• '.$membres[$r->membre_id]->nom_complet.' : '.$r->n.' mois, '.fcfa($r->du))->implode("\n");

        return $this->dire(
            "{$t['membres']} membre(s) en impayé, total ".fcfa($t['montant']).". Les principaux :\n{$liste}\nListe complète : menu « Impayés ».",
            "{$t['membres']} nit ñoo am bor, lépp ".fcfa($t['montant']).". Ñi ci ëpp :\n{$liste}",
        );
    }

    private function bilan(Analyse $a): string
    {
        $periode = $a->periodes ? Periode::fromString($a->periodes[0]) : (
            $a->moisSansAnnee ? Periode::fromString($this->periodeProbable($a->moisSansAnnee[0])) : Periode::courante());
        $s = $this->stats->periode($periode);
        $solde = fcfa($this->stats->soldeCaisse());
        $taux = str_replace('.', ',', (string) $s['taux']);

        return $this->dire(
            "{$periode->libelle()} : ".fcfa($s['encaisse']).' encaissés sur '.fcfa($s['attendu'])." ({$taux} %). {$s['a_jour']} membre(s) à jour, dont {$s['en_retard']} en retard ; {$s['impayes']} impayé(s), reste ".fcfa($s['reste']).". Solde de caisse : {$solde}.",
            "{$periode->libelle()} : dajale nañu ".fcfa($s['encaisse']).' ci '.fcfa($s['attendu'])." ({$taux} %). {$s['a_jour']} fey nañu, {$s['impayes']} feyagul. Xaalis bi ci caisse : {$solde}.",
        );
    }

    private function aide(bool $salutation): string
    {
        $nom = $this->parametres->get('ia_nom', 'Fatou');

        return $this->dire(
            ($salutation ? "Bonjour ! Je suis {$nom}. " : '')."Dites-moi par exemple :\n• « Encaisse Amy Tine 10 000 cash aujourd'hui »\n• « Babacar Dione a payé 2 mois par Wave réf 58213 »\n• « Combien doit Daouda Sene ? »\n• « Qui n'a pas payé ? » — « Bilan du mois »",
            ($salutation ? "Jamm rekk ! Maa ngi tudd {$nom}. " : '')."Misaal :\n• « Amy Tine dafa fey tey, ñaari junni cash »\n• « Babacar Dione jox na ñaari weer ci Wave, réf 58213 »\n• « Naata la Daouda Sene war ? »\n• « Ñan ñoo feyul ? »",
        );
    }

    /** Ligne courte bilingue (en-têtes de liste). */
    private function titre(string $fr, string $wo): string
    {
        return $this->wolof ? "{$wo} / {$fr}" : $fr;
    }

    /** Réponse bilingue quand l'utilisateur écrit en wolof (wolof puis français). */
    private function dire(string $fr, string $wo): string
    {
        return $this->wolof ? $wo."\n— ".$fr : $fr;
    }

    private function resultat(string $reponse, array $contexte, ?string $propositionId = null, ?string $action = null, bool $compris = true): array
    {
        return ['reponse' => $reponse, 'proposition_id' => $propositionId, 'contexte' => $contexte, 'action' => $action, 'compris' => $compris];
    }
}
