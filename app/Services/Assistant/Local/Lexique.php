<?php

namespace App\Services\Assistant\Local;

/**
 * Vocabulaire du moteur local (français, wolof, écriture SMS). Tous les mots sont
 * sous forme normalisée : minuscules, sans accents, « ñ » écrit « n », « ë » écrit « e ».
 * Pour enrichir la compréhension, il suffit d'ajouter des mots dans ces listes.
 */
final class Lexique
{
    /** Verbes et mots d'un encaissement. */
    public const PAIEMENT = [
        'encaisse', 'encaisser', 'encaissement', 'encaissements', 'encaissez', 'encaissee', 'encaise', 'encaiser', 'encese', 'ancaisse',
        'enregistre', 'enregistrer', 'enregistrement', 'enregistrez', 'enregistree', 'enrigistre', 'saisis', 'saisir', 'saisie', 'note', 'noter', 'notez',
        'paiement', 'paiment', 'payement', 'payment', 'paye', 'payee', 'payer', 'payes', 'payez', 'paie', 'paient', 'paies', 'payé',
        'verse', 'verser', 'versement', 'versee', 'donne', 'donner', 'donnee', 'remis', 'remet', 'remise', 'apporte', 'apporter', 'ramene',
        'cotise', 'cotiser', 'cotisation', 'cotisations', 'regle', 'regler', 'reglement', 'reglee', 'depose', 'deposer', 'envoye', 'envoyer', 'transfere',
        // wolof
        'fey', 'feye', 'feyal', 'feyna', 'feyeel', 'feyoon', 'jox', 'joxe', 'joxna', 'joxnaa', 'joxoon', 'indi', 'indil', 'indina', 'yonnee', 'yonne', 'yonnee',
        'dugal', 'dugalal', 'dugalalma', 'bindal', 'bind', 'bindalma', 'natt', 'teral', 'jeli', 'jel',
    ];

    /** Mots d'une question sur la situation d'un membre. */
    public const SITUATION = [
        'situation', 'combien', 'doit', 'dois', 'devoir', 'reste', 'restant', 'dette', 'dettes', 'arriere', 'arrieres', 'impaye', 'retard',
        'verifie', 'verifier', 'regarde', 'regarder', 'consulte', 'consulter', 'historique', 'etat', 'deja', 'ou',
        // wolof
        'bor', 'naata', 'ana', 'lan', 'war', 'wara', 'feyagul', 'feyul', 'seetal', 'seet', 'xoolal', 'xool',
    ];

    public const IMPAYES = [
        'impayes', 'impayees', 'impaye', 'retardataires', 'retardataire', 'retards', 'debiteurs', 'feyul', 'feyunu', 'feyagul', 'mauvais', 'relance', 'relancer',
    ];

    public const BILAN = [
        'bilan', 'total', 'totaux', 'recap', 'recapitulatif', 'resume', 'point', 'stat', 'stats', 'statistique', 'statistiques',
        'collecte', 'collecter', 'ramasse', 'caisse', 'recette', 'recettes', 'dajale', 'dajaleel', 'dajaloo', 'taux',
    ];

    public const AIDE = ['aide', 'aider', 'help', 'aidez', 'ndimbal', 'ndimbalal', 'comment', 'exemple', 'exemples', 'marche'];

    public const SALUTATIONS = [
        'bonjour', 'bonsoir', 'salut', 'slt', 'bjr', 'bsr', 'hello', 'coucou', 'salam', 'salamalekum', 'salamaleykum', 'asalamaleykum',
        'assalamou', 'alaykoum', 'aleykoum', 'nanga', 'naka', 'jamm', 'mangi', 'fi', 'rekk', 'maalekum',
    ];

    public const MERCI = ['merci', 'mrc', 'mci', 'jerejef', 'jerrejef', 'jeregef', 'jarajeff', 'thanks', 'thank'];

    public const OUI = [
        'oui', 'ouais', 'ouai', 'ok', 'okay', 'oki', 'okk', 'dac', 'daccord', 'waaw', 'waw', 'waow', 'wau', 'dakor', 'dakkoor', 'dakkor', 'dakoor',
        'confirme', 'confirmer', 'confirmez', 'confirmation', 'valide', 'valider', 'validez', 'yes', 'yep', 'parfait', 'go', 'baax', 'bax', 'correct', 'exact', 'cest', 'bon',
    ];

    public const NON = [
        'non', 'no', 'nope', 'deedeet', 'dedet', 'deedet', 'dedeet', 'annule', 'annuler', 'annulez', 'stop', 'laisse', 'laisser', 'oublie', 'oublier',
        'bayyil', 'bayil', 'bayi', 'bayyi', 'faux', 'erreur',
    ];

    /** Mots « outils » ignorés lors de la recherche du nom. */
    public const VIDES = [
        'le', 'la', 'les', 'l', 'de', 'du', 'des', 'd', 'un', 'une', 'a', 'au', 'aux', 'et', 'en', 'pour', 'par', 'avec', 'sur', 'ce', 'cet', 'cette', 'ces', 'ci',
        'il', 'elle', 'ils', 'elles', 'on', 'nous', 'vous', 'je', 'j', 'tu', 'te', 'me', 'm', 'moi', 'lui', 'son', 'sa', 'ses', 'leur', 'que', 'qui', 'quoi',
        'est', 'c', 's', 'y', 'ne', 'n', 'pas', 'plus', 'mr', 'mme', 'madame', 'monsieur', 'mlle', 'm', 'svp', 'stp', 'plait', 'sil', 'please', 'veuillez',
        'fais', 'faire', 'fait', 'faites', 'mets', 'mettre', 'met', 'ajoute', 'ajouter', 'nouveau', 'nouvelle', 'un', 'peux', 'peut', 'pourrais', 'voudrais', 'veux',
        'argent', 'somme', 'montant', 'fcfa', 'cfa', 'francs', 'franc', 'frs', 'f', 'moi', 'mon', 'ma', 'mes', 'notre', 'nos', 'votre', 'aussi', 'encore', 'maintenant',
        'membre', 'agent', 'camarade', 'collegue', 'frere', 'soeur', 'tonton', 'tata', 'vieux', 'grand', 'petit', 'avant', 'apres', 'depuis', 'jusqu', 'jusqua',
        't', 'a', 'ete', 'avoir', 'etre', 'va', 'vais', 'bien', 'dans', 'cotisant', 'recu', 'recus', 'mensuelle', 'mensuel', 'avance', 'avances', 'anticipe', 'anticipation', 'tard', 'retard', 'meme', 'celui', 'celle', 'bien',
        'plutot', 'mieux', 'finalement', 'change', 'changer', 'corrige', 'corriger', 'modifie', 'modifier', 'remplace', 'mais', 'enfin', 'donc', 'alors', 'sinon',
        'soxna', 'sokhna', 'si', 'oustaz', 'ustaz', 'oustaze', 'imam', 'docteur', 'dr', 'professeur', 'prof', 'chef', 'president', 'tresorier', 'secretaire', 'maire', 'adjoint',
        'coup', 'fois', 'partiel', 'partielle', 'complet', 'complete', 'mungi', 'mangi', 'nanu', 'nanyu', 'nau', 'date', 'mode', 'paiement',
        'seulement', 'juste', 'uniquement', 'directement', 'vite', 'rapidement', 'part', 'mooy', 'lii', 'loolu',
        // wolof
        'ak', 'ba', 'bi', 'bu', 'wi', 'wu', 'yi', 'mu', 'mi', 'ko', 'ma', 'na', 'nga', 'naa', 'dafa', 'dina', 'dama', 'ngir', 'moo', 'nu', 'sa', 'sama', 'am', 'def',
        'fa', 'leegi', 'legi', 'rek', 'it', 'tamit', 'ndax', 'dafay', 'kii', 'kooku', 'boobu', 'bii', 'boo', 'biir', 'ci', 'xaalis', 'kaalis', 'danga', 'damay',
        'nit', 'ki', 'kenn', 'ku', 'kan', 'ma', 'nak', 'waaye', 'te', 'la', 'lu', 'loo', 'nanu', 'nan', 'yow', 'man', 'moom', 'noom', 'nun', 'yeen',
    ];

    public const MOIS = [
        'janvier' => 1, 'janv' => 1, 'jan' => 1, 'janvie' => 1,
        'fevrier' => 2, 'fevr' => 2, 'fev' => 2, 'fevrie' => 2, 'febrier' => 2,
        'mars' => 3,
        'avril' => 4, 'avr' => 4, 'avrile' => 4,
        'mai' => 5,
        'juin' => 6,
        'juillet' => 7, 'juil' => 7, 'juilet' => 7, 'juiller' => 7,
        'aout' => 8, 'aou' => 8, 'aut' => 8, 'out' => 8,
        'septembre' => 9, 'sept' => 9, 'sep' => 9, 'septenbre' => 9, 'setembre' => 9,
        'octobre' => 10, 'oct' => 10, 'octobr' => 10,
        'novembre' => 11, 'nov' => 11, 'novenbre' => 11,
        'decembre' => 12, 'dec' => 12, 'decenbre' => 12,
    ];

    /** Jours de la semaine (ISO : 1 = lundi). */
    public const JOURS = [
        'lundi' => 1, 'mardi' => 2, 'mercredi' => 3, 'jeudi' => 4, 'vendredi' => 5, 'samedi' => 6, 'dimanche' => 7,
        'altine' => 1, 'talaata' => 2, 'allarba' => 3, 'alarba' => 3, 'alxames' => 4, 'alkhames' => 4, 'ajjuma' => 5, 'ajuma' => 5, 'gaawu' => 6, 'gawu' => 6, 'dibeer' => 7, 'dimaas' => 7,
    ];

    /** Nombres en toutes lettres (français). */
    public const NOMBRES_FR = [
        'zero' => 0, 'un' => 1, 'une' => 1, 'deux' => 2, 'trois' => 3, 'quatre' => 4, 'cinq' => 5, 'six' => 6, 'sept' => 7, 'huit' => 8, 'neuf' => 9,
        'dix' => 10, 'onze' => 11, 'douze' => 12, 'treize' => 13, 'quatorze' => 14, 'quinze' => 15, 'seize' => 16,
        'vingt' => 20, 'vingts' => 20, 'trente' => 30, 'quarante' => 40, 'cinquante' => 50, 'soixante' => 60,
        'cent' => 100, 'cents' => 100, 'mille' => 1000, 'milles' => 1000,
    ];

    /** Nombres wolof (avec la forme de liaison en « -i » : ñaari, ñetti…). */
    public const NOMBRES_WO = [
        'benn' => 1, 'ben' => 1, 'naar' => 2, 'naari' => 2, 'nett' => 3, 'netti' => 3, 'net' => 3, 'neent' => 4, 'neenti' => 4, 'nent' => 4,
        'juroom' => 5, 'juroomi' => 5, 'jurom' => 5, 'juromi' => 5, 'fukk' => 10, 'fukki' => 10, 'fuk' => 10, 'fuki' => 10,
        'teemeer' => 100, 'teemeeri' => 100, 'temer' => 100, 'temeer' => 100, 'junni' => 1000, 'junne' => 1000, 'juni' => 1000,
    ];

    /** Nombre de mois : « deux mois », « ñaari weer ». */
    public const COMPTES = [
        'un' => 1, 'une' => 1, 'deux' => 2, 'trois' => 3, 'quatre' => 4, 'cinq' => 5, 'six' => 6, 'sept' => 7, 'huit' => 8, 'neuf' => 9, 'dix' => 10, 'onze' => 11, 'douze' => 12,
        'benn' => 1, 'ben' => 1, 'naar' => 2, 'naari' => 2, 'nett' => 3, 'netti' => 3, 'neent' => 4, 'neenti' => 4, 'juroom' => 5, 'juroomi' => 5,
        'juroom benn' => 6, 'juroomi benn' => 6, 'juroom naar' => 7, 'juroom nett' => 8, 'juroom neent' => 9, 'fukk' => 10, 'fukki' => 10,
    ];

    public const ORDINAUX = [
        'premier' => 1, 'premiere' => 1, '1er' => 1, '1ere' => 1, 'njekk' => 1, 'njeek' => 1,
        'deuxieme' => 2, '2eme' => 2, '2e' => 2, 'second' => 2, 'seconde' => 2, 'naareel' => 2, 'naarel' => 2,
        'troisieme' => 3, '3eme' => 3, '3e' => 3, 'netteel' => 3, 'nettel' => 3,
        'quatrieme' => 4, '4eme' => 4, '4e' => 4, 'neenteel' => 4,
        'cinquieme' => 5, '5eme' => 5, '5e' => 5,
    ];

    /** Modes de paiement : expression → code (les expressions longues sont testées en premier). */
    public const MODES = [
        'orange money' => 'orange_money', 'orange mony' => 'orange_money', 'o m' => 'orange_money', 'orangemoney' => 'orange_money', 'orange' => 'orange_money', 'om' => 'orange_money',
        'wave' => 'wave', 'wav' => 'wave', 'ouave' => 'wave', 'waave' => 'wave', 'weiv' => 'wave', 'wawe' => 'wave',
        'en main propre' => 'especes', 'main propre' => 'especes', 'en liquide' => 'especes', 'ci loxo' => 'especes',
        'especes' => 'especes', 'espece' => 'especes', 'espaces' => 'especes', 'cash' => 'especes', 'cach' => 'especes', 'kash' => 'especes', 'liquide' => 'especes', 'loxo' => 'especes',
        'virement' => 'virement', 'vire' => 'virement', 'banque' => 'virement', 'bancaire' => 'virement', 'bank' => 'virement', 'banq' => 'virement',
        'cheque' => 'cheque', 'chek' => 'cheque', 'chq' => 'cheque', 'check' => 'cheque',
        'free money' => 'autre', 'wizall' => 'autre', 'e money' => 'autre', 'emoney' => 'autre', 'kpay' => 'autre',
    ];

    /** Variantes d'écriture → forme de référence (prénoms et noms courants au Sénégal). */
    public const ALIAS_NOMS = [
        // prénoms
        'moodu' => 'modou', 'modu' => 'modou', 'moudou' => 'modou', 'mamadu' => 'mamadou', 'mamdou' => 'mamadou',
        'useynu' => 'ousseynou', 'usseynu' => 'ousseynou', 'ouseynou' => 'ousseynou', 'ousseinou' => 'ousseynou', 'ouseinou' => 'ousseynou', 'seynou' => 'ousseynou',
        'abdu' => 'abdou', 'abdoul' => 'abdou', 'ibrayima' => 'ibrahima', 'ibraima' => 'ibrahima', 'ibrahim' => 'ibrahima', 'ibraa' => 'ibra',
        'seex' => 'cheikh', 'sheikh' => 'cheikh', 'cheik' => 'cheikh', 'chekh' => 'cheikh', 'cheick' => 'cheikh', 'cheikhou' => 'cheikh',
        'serin' => 'serigne', 'sering' => 'serigne', 'sergne' => 'serigne', 'serign' => 'serigne', 'serinn' => 'serigne',
        'faatu' => 'fatou', 'fatu' => 'fatou', 'fatoo' => 'fatou', 'ndey' => 'ndeye', 'ndeiye' => 'ndeye', 'ndeye' => 'ndeye',
        'maam' => 'mame', 'mam' => 'mame', 'aliyu' => 'aliou', 'aliu' => 'aliou', 'baabakar' => 'babacar', 'babakar' => 'babacar', 'bacar' => 'babacar',
        'dawda' => 'daouda', 'dauda' => 'daouda', 'daoda' => 'daouda', 'jibi' => 'djiby', 'jiby' => 'djiby', 'djibi' => 'djiby', 'djibril' => 'djiby',
        'cerno' => 'thierno', 'tierno' => 'thierno', 'sulei' => 'souleye', 'souley' => 'souleye', 'suleye' => 'souleye', 'souleymane' => 'souleye',
        'xadim' => 'khadim', 'kadim' => 'khadim', 'xaadim' => 'khadim', 'musaa' => 'moussa', 'musa' => 'moussa', 'mousa' => 'moussa', 'moor' => 'mor',
        'yusu' => 'youssou', 'yousou' => 'youssou', 'yusou' => 'youssou', 'daru' => 'darou', 'bineeta' => 'bineta', 'aawa' => 'awa', 'mari' => 'marie', 'mary' => 'marie',
        'asiis' => 'aziz', 'azis' => 'aziz', 'aram' => 'arame', 'aaram' => 'arame', 'lamin' => 'lamine', 'laamin' => 'lamine', 'latir' => 'latyr', 'laatir' => 'latyr',
        'basiru' => 'bassirou', 'bassiru' => 'bassirou', 'basirou' => 'bassirou', 'eliman' => 'elimane', 'elimaan' => 'elimane', 'babukar' => 'baboucar', 'baboukar' => 'baboucar',
        'meguey' => 'megueye', 'megey' => 'megueye', 'bara' => 'barra', 'jeynaba' => 'dieynaba', 'dieynab' => 'dieynaba', 'jenaba' => 'dieynaba', 'dienaba' => 'dieynaba',
        'gaan' => 'gane', 'ayda' => 'aida', 'aissatou' => 'aida', 'faatim' => 'fatime', 'maxuja' => 'makhoudia', 'makhoudja' => 'makhoudia', 'njaat' => 'ndiatte',
        'paap' => 'pape', 'pap' => 'pape', 'adaama' => 'adama', 'mbakke' => 'mbacke', 'mbake' => 'mbacke', 'xaadir' => 'khadre', 'khadir' => 'khadre', 'kadre' => 'khadre',
        'basin' => 'bassine', 'jaara' => 'diarra', 'jara' => 'diarra', 'diara' => 'diarra', 'elhadji' => 'hadji', 'hadj' => 'hadji', 'aladji' => 'hadji', 'elhadj' => 'hadji', 'alhadji' => 'hadji',
        'ami' => 'amy', 'amie' => 'amy', 'aamy' => 'amy',
        // noms
        'seen' => 'sene', 'sen' => 'sene', 'senne' => 'sene', 'tiin' => 'tine', 'tin' => 'tine', 'thine' => 'tine', 'ngning' => 'gning', 'niing' => 'gning', 'gniing' => 'gning', 'ning' => 'gning',
        'ngoom' => 'ngom', 'fay' => 'faye', 'faay' => 'faye', 'njaay' => 'ndiaye', 'ndiay' => 'ndiaye', 'ndiaay' => 'ndiaye', 'ndaye' => 'ndiaye',
        'jon' => 'dione', 'joon' => 'dione', 'dion' => 'dione', 'diome' => 'dione', 'caw' => 'thiao', 'tiao' => 'thiao', 'thiaw' => 'thiao', 'caaw' => 'thiao', 'tiaw' => 'thiao',
        'juuf' => 'diouf', 'djouf' => 'diouf', 'juf' => 'diouf', 'jallo' => 'diallo', 'djallo' => 'diallo', 'dialo' => 'diallo',
        'kanji' => 'kandji', 'kanje' => 'kandji', 'kandj' => 'kandji', 'kandiy' => 'kandji', 'sung' => 'soung', 'jaan' => 'diagne', 'diane' => 'diagne', 'diagn' => 'diagne',
        'gey' => 'gueye', 'guey' => 'gueye', 'geey' => 'gueye', 'gueuye' => 'gueye', 'naang' => 'niang', 'njang' => 'niang', 'niangue' => 'niang',
        'saamb' => 'samb', 'sise' => 'cisse', 'sisse' => 'cisse', 'cise' => 'cisse', 'gay' => 'gaye', 'gaay' => 'gaye', 'saar' => 'sarr', 'sar' => 'sarr',
        'puy' => 'pouye', 'pouy' => 'pouye', 'puuy' => 'pouye', 'sila' => 'sylla', 'silla' => 'sylla', 'syla' => 'sylla',
        'caam' => 'thiam', 'tiam' => 'thiam', 'joob' => 'diop', 'jop' => 'diop', 'djop' => 'diop', 'faal' => 'fall', 'fal' => 'fall', 'mbaay' => 'mbaye', 'mbay' => 'mbaye', 'soow' => 'sow',
    ];

    /** Mots signalant du wolof (pour répondre dans la même langue). */
    public const MARQUEURS_WOLOF = [
        'dafa', 'fey', 'feyna', 'tey', 'tay', 'demb', 'weer', 'jox', 'joxna', 'bindal', 'dugal', 'xaalis', 'loxo', 'naari', 'netti', 'junni', 'bor', 'naata',
        'waaw', 'deedeet', 'jerejef', 'nanga', 'mangi', 'yepp', 'lepp', 'feyul', 'feyagul', 'wii', 'weesu', 'ndax', 'dama', 'dina', 'ngir', 'ak', 'ci', 'dakor', 'lan', 'kan',
    ];

    /** Mots-clés à ne jamais confondre avec un nom de membre. */
    public static function reserves(): array
    {
        static $cache = null;

        return $cache ??= array_fill_keys(array_merge(
            self::PAIEMENT, self::SITUATION, self::IMPAYES, self::BILAN, self::AIDE, self::SALUTATIONS, self::MERCI, self::OUI, self::NON, self::VIDES,
            array_keys(self::MOIS), array_keys(self::JOURS), array_keys(self::NOMBRES_FR), array_keys(self::NOMBRES_WO), array_keys(self::ORDINAUX),
            array_merge(...array_map(fn ($k) => explode(' ', $k), array_keys(self::MODES))),
            ['mois', 'weer', 'wer', 'month', 'aujourd', 'hui', 'aujourdhui', 'auj', 'jour', 'tey', 'tay', 'hier', 'demb', 'dem', 'berki', 'dernier', 'derniere', 'passe',
                'prochain', 'prochaine', 'suivant', 'courant', 'cours', 'wii', 'weesu', 'new', 'ref', 'reference', 'referans', 'transaction', 'numero', 'num', 'matricule',
                'tout', 'toute', 'tous', 'toutes', 'lepp', 'yepp', 'totalite', 'derem', 'dereem', 'drm', 'k', 'matin', 'soir', 'suba', 'ngoon', 'today', 'le', 'er'],
        ), true);
    }
}
