<?php

namespace App\Services\Assistant\Local;

use App\Support\Periode;
use Carbon\CarbonImmutable;

/**
 * Analyse d'une phrase (français, wolof ou mélange) sans intelligence artificielle
 * externe : extraction par règles et vocabulaire (voir Lexique).
 */
final class Analyseur
{
    /** Phrase normalisée en cours d'analyse ; les éléments reconnus en sont retirés. */
    private string $s = '';

    public function __construct(
        private string $nomAssistant = 'fatou',
        private string $prefixeMatricule = 'ngd',
        private array $modesActifs = [],
    ) {
        $this->nomAssistant = Texte::normaliser($nomAssistant);
        $this->prefixeMatricule = trim(preg_replace('/[^a-z0-9]/', '', Texte::normaliser($prefixeMatricule)));
    }

    public function analyser(string $message, ?CarbonImmutable $aujourdhui = null): Analyse
    {
        $a = new Analyse;
        $a->texte = $message;
        $jour = $aujourdhui ?? CarbonImmutable::today();
        $this->s = ' '.Texte::normaliser($message).' ';

        $a->question = str_contains($message, '?')
            || (bool) preg_match('/^\s*(est ce que|est-ce que|combien|ou en est|qui|quel|quelle|quels|a t il|a t elle|ndax|ana|naata|lan|kan|ki)\b/', trim($this->s));

        $this->retirerVocatif();
        $this->extraireReference($a, $message);
        $this->extraireMatriculeEtTelephone($a);
        $this->extraireDates($a, $jour);
        $this->extraireToutEtComptes($a);
        $this->extrairePeriodes($a, $jour);
        $this->extraireMontants($a);
        $this->extraireMode($a);

        $mots = Texte::mots($this->s);
        $reserves = Lexique::reserves();
        foreach ($mots as $mot) {
            $a->paiement = $a->paiement || Texte::correspond($mot, Lexique::PAIEMENT);
            $a->situation = $a->situation || in_array($mot, Lexique::SITUATION, true);
            $a->impayes = $a->impayes || in_array($mot, Lexique::IMPAYES, true);
            $a->bilan = $a->bilan || in_array($mot, Lexique::BILAN, true);
            $a->aide = $a->aide || in_array($mot, Lexique::AIDE, true);
            $a->salutation = $a->salutation || in_array($mot, Lexique::SALUTATIONS, true);
            $a->merci = $a->merci || in_array($mot, Lexique::MERCI, true);
            $a->oui = $a->oui || in_array($mot, Lexique::OUI, true);
            $a->non = $a->non || in_array($mot, Lexique::NON, true);
            if (isset(Lexique::ORDINAUX[$mot])) {
                $a->ordinal ??= Lexique::ORDINAUX[$mot];
            }
            if (ctype_digit($mot) && (int) $mot < 1000) {
                $a->petitsNombres[] = (int) $mot;
            } elseif (! isset($reserves[$mot]) && ! ctype_digit($mot) && strlen($mot) >= 2) {
                $a->motsNom[] = $mot;
            }
        }
        // Expressions de plusieurs mots.
        $t = ' '.Texte::normaliser($message).' ';
        $a->impayes = $a->impayes || (bool) preg_match('/\b(pas (encore )?paye|pas (encore )?regle|n ont pas|ku feyul|nu feyul|nan nu|nan nan|qui doit|qui doivent|feyuwul)\b/', $t);
        $a->bilan = $a->bilan || (bool) preg_match('/\b(combien (on|nous|avons|a t on|est ce qu on)|situation (du|de ce) mois|point du mois|naata lanu|naata la nu|naata lan)\b/', $t);
        $a->aide = $a->aide || (bool) preg_match('/\b(que sais tu|tu sais faire|qu est ce que tu (sais|peux)|lan nga man|lan ngay def)\b/', $t);
        $a->salutation = $a->salutation || (bool) preg_match('/\b(nanga def|naka nga def|na nga def|salamu alaykum)\b/', $t);
        $a->oui = $a->oui || (bool) preg_match('/\b(c est bon|vas y|allez y|d accord|baax na|mangi dakor|mungi dakor)\b/', $t);
        $a->non = $a->non || (bool) preg_match('/\b(pas ca|ne pas|laisse tomber)\b/', $t) && ! $a->paiement;

        $a->wolof = count(array_intersect(Texte::mots($t), Lexique::MARQUEURS_WOLOF)) >= 2
            || (bool) preg_match('/\b(dafa fey|fey na|jox na|nanga def|jerejef|waaw|deedeet|ci wave|ci loxo)\b/', $t);

        $a->motsNom = array_values(array_unique($a->motsNom));

        return $a;
    }

    private function remplacer(string $motif, ?callable $rappel = null): void
    {
        $this->s = $rappel ? preg_replace_callback($motif, $rappel, $this->s) : preg_replace($motif, ' ', $this->s);
    }

    /** « Fatou, encaisse… » : l'assistante est interpellée, ce n'est pas un nom de membre. */
    private function retirerVocatif(): void
    {
        $n = preg_quote($this->nomAssistant, '/');
        $commandes = 'encaisse|encaisser|enregistre|note|bindal|dugal|fais|mets|ajoute|combien|qui|donne|dis|montre|regarde|seetal|xoolal|est|ndax|naata|bonjour|salut|merci|jerejef|nanga|stp|svp|s il|peux|bilan|situation|aide';
        $this->s = preg_replace("/^\\s*(?:(?:bonjour|salut|bonsoir|salam|merci|jerejef|oui|non|waaw|ok)\\s+)?{$n}\\s*[,:!]/", ' ', $this->s);
        $this->s = preg_replace("/^\\s*{$n}\\s+(?=(?:{$commandes})\\b)/", ' ', $this->s);
        $this->s = preg_replace("/\\b(merci|jerejef|bonjour|salut|bonsoir|stp|svp)\\s+{$n}\\b/", '$1 ', $this->s);
        $this->s = preg_replace("/,\\s*{$n}\\s*[.!?]*\\s*$/", ' ', $this->s);
    }

    private function extraireReference(Analyse $a, string $original): void
    {
        $motif = '/\b(?:r[ée]f(?:[ée]rence)?s?|referans|transaction|trans|txn|id(?:\s+de\s+transaction)?|n[°o]\s*de\s*transaction)\s*(?:n[°o]\.?|num[ée]ro|:|#|=|est)?\s*((?=[A-Z0-9\-_\.]*\d)[A-Z0-9][A-Z0-9\-_\.]{2,})/iu';
        if (preg_match($motif, $original, $m) && ! preg_match('/^(de|du|la|le|est|pour)$/i', $m[1])) {
            $a->reference = strtoupper(rtrim($m[1], '.'));
            $this->s = str_ireplace(Texte::normaliser($m[0]), ' ', $this->s);
        }
    }

    private function extraireMatriculeEtTelephone(Analyse $a): void
    {
        $p = preg_quote($this->prefixeMatricule, '/');
        $this->remplacer('/\b(?:'.$p.'\s*-?\s*|matricule\s*(?:n\s*|no\s*|numero\s*)?)0*(\d{1,4})\b/', function ($m) use ($a) {
            $a->matricule = (string) (int) $m[1];

            return ' ';
        });
        $this->remplacer('/(?:\+?221[\s.]?)?\b(7[05-8](?:[\s.]?\d){7})\b/', function ($m) use ($a) {
            $a->telephone = preg_replace('/\D/', '', $m[1]);

            return ' ';
        });
    }

    private function extraireDates(Analyse $a, CarbonImmutable $jour): void
    {
        $fixer = function (CarbonImmutable $d) use ($a) {
            $a->date ??= $d->toDateString();

            return ' ';
        };
        $moisRegex = implode('|', array_map(fn ($m) => preg_quote($m, '/'), array_keys(Lexique::MOIS)));

        // 03/09/2026, 3-9-26, 3/9
        $this->remplacer('/\b(\d{1,2})[\/\-.](\d{1,2})(?:[\/\-.](\d{2,4}))?\b/', function ($m) use ($jour, $fixer) {
            $annee = isset($m[3]) ? ((int) $m[3] < 100 ? 2000 + (int) $m[3] : (int) $m[3]) : (int) $jour->format('Y');
            if (! checkdate((int) $m[2], (int) $m[1], $annee)) {
                return $m[0];
            }
            $d = CarbonImmutable::create($annee, (int) $m[2], (int) $m[1]);

            return $fixer(! isset($m[3]) && $d->gt($jour) ? $d->subYear() : $d);
        });
        // le 3 septembre (2026)
        $this->remplacer('/\b(?:le\s+)?(\d{1,2}|premier)(?:er)?\s+('.$moisRegex.')\b(?:\s+(20\d{2}))?/', function ($m) use ($jour, $fixer) {
            $mois = Lexique::MOIS[$m[2]];
            $annee = isset($m[3]) ? (int) $m[3] : (int) $jour->format('Y');
            $j = $m[1] === 'premier' ? 1 : (int) $m[1];
            if (! checkdate($mois, $j, $annee)) {
                return $m[0];
            }
            $d = CarbonImmutable::create($annee, $mois, $j);

            return $fixer(! isset($m[3]) && $d->gt($jour) ? $d->subYear() : $d);
        });
        // le 3 (du mois courant, ou du précédent si ce jour n'est pas encore passé)
        $this->remplacer('/\ble\s+(\d{1,2})(?:er)?\b(?!\s*(?:mois|weer|mille|000|k\b|f\b|fr|fcfa))/', function ($m) use ($jour, $fixer) {
            $j = (int) $m[1];
            if ($j < 1 || $j > 31) {
                return $m[0];
            }
            $d = $jour->day(min($j, $jour->daysInMonth));

            return $fixer($d->gt($jour) ? $jour->subMonthNoOverflow()->day(min($j, $jour->subMonthNoOverflow()->daysInMonth)) : $d);
        });

        $relatifs = [
            '/\b(avant\s*hier|avant\s*d\s*hier|berki\s*demb|berkidemb)\b/' => 2,
            '/\b(aujourd\s*hui|aujourdhui|aujourdui|auj|ce\s+jour|tey|tay|today|ce\s+matin|cet\s+apres\s+midi|ce\s+soir|tey\s+ci\s+suba|leegi|legi|maintenant|a\s+l\s+instant)\b/' => 0,
            '/\b(hier|yer|demb|dem|yesterday)\b/' => 1,
        ];
        foreach ($relatifs as $motif => $jours) {
            $this->remplacer($motif, fn () => $fixer($jour->subDays($jours)));
        }
        $joursRegex = implode('|', array_keys(Lexique::JOURS));
        $this->remplacer('/\b('.$joursRegex.')(?:\s+(?:dernier|passe|bi\s+weesu|bi\s+dem))?\b/', function ($m) use ($jour, $fixer) {
            $cible = Lexique::JOURS[$m[1]];
            $ecart = ($jour->dayOfWeekIso - $cible + 7) % 7;

            return $fixer($jour->subDays($ecart));
        });
    }

    private function extraireToutEtComptes(Analyse $a): void
    {
        $this->remplacer('/\b(tout\s+ce\s+qu\s+(il|elle)\s+doi[st]|tous\s+(ses|les)\s+(mois|arrieres|impayes)(\s+dus)?|toute\s+(sa|la)\s+dette|la\s+totalite|tout\s+le\s+reste|tout|lepp|yepp|bor\s+bi\s+yepp|bor\s+bi\s+lepp)\b/',
            function () use ($a) {
                $a->tout = true;

                return ' ';
            });
        $comptes = implode('|', array_map(fn ($c) => str_replace(' ', '\s+', preg_quote($c, '/')), array_keys(Lexique::COMPTES)));
        $this->remplacer('/\b(\d{1,2}|'.$comptes.')\s*(?:derniers?\s+|premiers?\s+)?(mois|weer|wer|month)\b/', function ($m) use ($a) {
            $cle = preg_replace('/\s+/', ' ', $m[1]);
            $a->nbMois ??= ctype_digit($cle) ? (int) $cle : Lexique::COMPTES[$cle];

            return ' ';
        });
        // « benn weer », « weer » seul après « benn » déjà traité ; « un mois » traité ci-dessus.
    }

    private function extrairePeriodes(Analyse $a, CarbonImmutable $jour): void
    {
        $courante = Periode::fromDate($jour);
        $ajout = function (Periode $p) use ($a) {
            $a->periodes[] = (string) $p;

            return ' ';
        };
        $this->remplacer('/\b(mois\s+(dernier|passe|precedent)|le\s+mois\s+d\s+avant|weer\s+w[iu]\s+weesu|weer\s+w[iu]\s+dem|weesu)\b/', fn () => $ajout($courante->precedente()));
        $this->remplacer('/\b(mois\s+(prochain|suivant|a\s+venir)|weer\s+w[iu]\s+(di\s+)?new|weer\s+wu\s+new)\b/', fn () => $ajout($courante->suivante()));
        $this->remplacer('/\b(ce\s+mois(\s+ci)?|mois\s+(en\s+cours|courant|actuel)|weer\s+wii|weer\s+wi)\b/', fn () => $ajout($courante));

        $moisRegex = implode('|', array_map(fn ($m) => preg_quote($m, '/'), array_keys(Lexique::MOIS)));
        $trouves = [];
        $this->remplacer('/\b('.$moisRegex.')\b(?:\s+(20\d{2}))?/', function ($m) use (&$trouves) {
            $trouves[] = ['mois' => Lexique::MOIS[$m[1]], 'annee' => isset($m[2]) ? (int) $m[2] : null, 'position' => null];

            return ' #MOIS# ';
        });
        if (! $trouves) {
            return;
        }
        // Intervalle : « de juillet à septembre », « juillet ba septembre », « juillet-septembre ».
        $intervalle = count($trouves) === 2 && preg_match('/#MOIS#\s*(?:a|au|jusqu\s*a|jusqua|ba|-)\s*#MOIS#/', $this->s);
        $this->s = str_replace('#MOIS#', ' ', $this->s);
        if ($intervalle) {
            [$debut, $fin] = $trouves;
            $n = ($fin['mois'] - $debut['mois'] + 12) % 12;
            for ($i = 0; $i <= $n; $i++) {
                $trouves[] = ['mois' => ($debut['mois'] + $i - 1) % 12 + 1, 'annee' => null, 'intervalle' => true];
            }
            $trouves = array_slice($trouves, 2);
        }
        foreach ($trouves as $t) {
            if ($t['annee']) {
                $a->periodes[] = (string) new Periode($t['annee'], $t['mois']);
            } else {
                $a->moisSansAnnee[] = $t['mois'];
            }
        }
        $a->moisSansAnnee = array_values(array_unique($a->moisSansAnnee));
    }

    private function extraireMontants(Analyse $a): void
    {
        // Chiffres : 10000, 10 000, 10.000, 10k, 10 mille, 2000 dërëm
        $this->remplacer('/\b(\d{1,3}(?:[ .]\d{3})+|\d+)\s*(k|mille|milles|000)?\s*(f|fr|frs|fcfa|cfa|francs?|xof|derem|dereem|drm)?\b/', function ($m) use ($a) {
            $valeur = (int) preg_replace('/\D/', '', $m[1]);
            $multiplicateur = match ($m[2] ?? '') {
                'k', 'mille', 'milles' => 1000,
                '000' => 1000,
                default => 1,
            };
            $unite = $m[3] ?? '';
            $valeur *= $multiplicateur;
            if (in_array($unite, ['derem', 'dereem', 'drm'], true)) {
                $valeur *= 5;
            }
            if ($valeur < 100 && $unite === '' && $multiplicateur === 1) {
                return $m[0]; // petit nombre : choix, jour, matricule court…
            }
            $a->montant ??= $valeur;

            return ' ';
        });

        $mots = Texte::mots($this->s);
        // Nombres en lettres (français) : « dix mille », « vingt cinq mille »
        [$valeur, $sequence] = $this->lireNombre($mots, Lexique::NOMBRES_FR);
        if ($valeur !== null && $valeur >= 100) {
            $a->montant ??= $valeur;
            $this->retirerSequence($sequence);
            $mots = Texte::mots($this->s);
        }
        // Nombres wolof, exprimés en dërëm (1 dërëm = 5 FCFA) : « ñaari junni » = 10 000 FCFA
        [$valeur, $sequence] = $this->lireNombre($mots, Lexique::NOMBRES_WO, true);
        if ($valeur !== null && $valeur >= 100) {
            $enFrancs = (bool) preg_match('/\b('.implode('\s+', array_map('preg_quote', $sequence)).')\s+(f|fr|frs|fcfa|cfa|francs?)\b/', $this->s);
            $a->montant ??= $enFrancs ? $valeur : $valeur * 5;
            $this->retirerSequence($sequence);
        }
    }

    /**
     * Lit la première suite de mots-nombres (avec « et » / « ak »).
     *
     * @return array{0: ?int, 1: list<string>}
     */
    private function lireNombre(array $mots, array $dico, bool $wolof = false): array
    {
        $total = 0;
        $courant = 0;
        $sequence = [];
        $enCours = false;
        foreach ([...$mots, '#fin#'] as $mot) {
            $liaison = $wolof ? $mot === 'ak' : in_array($mot, ['et'], true);
            if (! isset($dico[$mot]) && ! ($enCours && $liaison)) {
                if ($enCours) {
                    // Fin d'une suite : on la garde si c'est un montant plausible, sinon on cherche plus loin.
                    if ($total + $courant >= 100) {
                        break;
                    }
                    [$total, $courant, $sequence, $enCours] = [0, 0, [], false];
                }

                continue;
            }
            $enCours = true;
            $sequence[] = $mot;
            if ($liaison) {
                continue;
            }
            $v = $dico[$mot];
            if ($v === 1000) {
                $total += max(1, $courant) * 1000;
                $courant = 0;
            } elseif ($v === 100) {
                $courant = max(1, $courant) * 100;
            } elseif ($wolof && $v === 10 && $courant > 0 && $courant < 10) {
                $courant *= 10; // « ñaar fukk » = 20
            } else {
                $courant += $v;
            }
        }

        return $sequence ? [$total + $courant, $sequence] : [null, []];
    }

    private function retirerSequence(array $sequence): void
    {
        if ($sequence) {
            $this->s = preg_replace('/\b'.implode('\s+', array_map(fn ($m) => preg_quote($m, '/'), $sequence)).'\b/', ' ', $this->s, 1);
        }
    }

    private function extraireMode(Analyse $a): void
    {
        $modes = Lexique::MODES;
        foreach ($this->modesActifs as $code => $nom) {
            $modes[Texte::normaliser($nom)] ??= $code;
        }
        uksort($modes, fn ($x, $y) => strlen($y) <=> strlen($x));
        foreach ($modes as $expression => $code) {
            $motif = '/\b(?:par\s+|en\s+|via\s+|ci\s+)?'.str_replace(' ', '\s+', preg_quote($expression, '/')).'\b/';
            if (preg_match($motif, $this->s)) {
                $a->mode = $code;
                $this->s = preg_replace($motif, ' ', $this->s, 1);

                return;
            }
        }
    }
}
