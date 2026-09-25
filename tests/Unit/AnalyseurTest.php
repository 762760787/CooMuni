<?php

namespace Tests\Unit;

use App\Services\Assistant\Local\Analyseur;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Corpus d'« entraînement » du moteur local : chaque phrase (français, wolof,
 * écriture SMS, fautes de frappe) et ce qu'il doit en comprendre.
 * Pour enrichir la compréhension : ajouter une phrase ici, puis le vocabulaire dans Lexique.
 * Date de référence : jeudi 24 septembre 2026.
 */
class AnalyseurTest extends TestCase
{
    public static function corpus(): array
    {
        return [
            // --- Encaissements en français ---
            ['Fatou, fais un encaissement de Amy Tine aujourd\'hui', ['paiement' => true, 'motsNom' => ['amy', 'tine'], 'date' => '2026-09-24']],
            ['Encaisse Babacar Dione 20 000 en espèces hier', ['paiement' => true, 'montant' => 20000, 'mode' => 'especes', 'date' => '2026-09-23', 'motsNom' => ['babacar', 'dione']]],
            ['babacar dione a payé 20000f par OM ref ABC12345', ['montant' => 20000, 'mode' => 'orange_money', 'reference' => 'ABC12345']],
            ['Modou Sene a payé septembre et octobre', ['moisSansAnnee' => [9, 10], 'motsNom' => ['modou', 'sene']]],
            ['Aliou Tine a payé de juillet à septembre par wave réf 998877', ['moisSansAnnee' => [7, 8, 9], 'mode' => 'wave', 'reference' => '998877']],
            ['Ibrahima Sene 10k wave ref 55667 le 3 septembre', ['montant' => 10000, 'mode' => 'wave', 'reference' => '55667', 'date' => '2026-09-03']],
            ['Penda Ngom paie 3 mois', ['paiement' => true, 'nbMois' => 3, 'motsNom' => ['penda', 'ngom']]],
            ['Bineta Pouye a versé cinq mille', ['montant' => 5000, 'motsNom' => ['bineta', 'pouye']]],
            ['Mame Diarra Gning a payé tout ce qu\'elle doit', ['tout' => true, 'motsNom' => ['mame', 'diarra', 'gning']]],
            ['Modou Sene NGD-058 10000', ['matricule' => '58', 'montant' => 10000]],
            ['Awa Sene a payé le 02/09/2026 en cash', ['date' => '2026-09-02', 'mode' => 'especes']],
            ['Fatou Tine a payé lundi', ['date' => '2026-09-21', 'motsNom' => ['fatou', 'tine']]],
            ['Amy Tine a payé le mois dernier', ['periodes' => ['2026-08'], 'motsNom' => ['amy', 'tine']]],
            ['Ndeye Ngom a payé 15.000 fcfa le 20/09', ['montant' => 15000, 'date' => '2026-09-20']],
            ['enregistre 2 mois pour Khadim Dione par Wave, référence W-7788', ['nbMois' => 2, 'mode' => 'wave', 'reference' => 'W-7788', 'motsNom' => ['khadim', 'dione']]],
            ['Cheikh Kandji a payé octobre en avance', ['moisSansAnnee' => [10], 'motsNom' => ['cheikh', 'kandji']]],
            ['le membre 77 123 45 67 a payé', ['telephone' => '771234567']],
            ['Encaisse vingt-cinq mille pour Thierno Tine', ['montant' => 25000]],
            ['Fatou encaisse Fatou Ngom 10000 avant-hier', ['montant' => 10000, 'date' => '2026-09-22', 'motsNom' => ['fatou', 'ngom']]],
            ['encaisse amy tine dix mille francs espèces aujourd\'hui', ['montant' => 10000, 'mode' => 'especes', 'date' => '2026-09-24']],
            ['Amy Tine a payé le premier septembre', ['date' => '2026-09-01', 'moisSansAnnee' => []]],
            ['Mbaye Ngom 10.000F wave ref 8877 hier', ['montant' => 10000, 'mode' => 'wave', 'reference' => '8877', 'date' => '2026-09-23', 'motsNom' => ['mbaye', 'ngom']]],
            ['Enregistre le paiement de Awa Sene: 10000 FCFA, Wave, réf: 1234567, date 20/09/2026', ['montant' => 10000, 'mode' => 'wave', 'reference' => '1234567', 'date' => '2026-09-20', 'motsNom' => ['awa', 'sene']]],
            ['Encaisser 10000 pour le matricule 45', ['matricule' => '45', 'montant' => 10000]],
            ['encaisse NGD 78', ['matricule' => '78']],
            ['Oustaz Moussa Thiao a payé 2 mois', ['nbMois' => 2, 'motsNom' => ['moussa', 'thiao']]],
            ['Latyr Gning a payé 3 mois d\'un coup', ['nbMois' => 3, 'motsNom' => ['latyr', 'gning']]],
            // --- Écriture SMS, fautes de frappe ---
            ['Encaise Amy Tinne 10000', ['paiement' => true, 'montant' => 10000, 'motsNom' => ['amy', 'tinne']]],
            ['amy tine a paye 10 mille cash auj', ['montant' => 10000, 'mode' => 'especes', 'date' => '2026-09-24']],
            ['paiment moussa diouf 2mois ouave ref 4455', ['nbMois' => 2, 'mode' => 'wave', 'reference' => '4455']],
            // --- Wolof ---
            ['Amy Tine dafa fey ñaari weer tey ci Wave', ['paiement' => true, 'nbMois' => 2, 'mode' => 'wave', 'date' => '2026-09-24', 'wolof' => true, 'motsNom' => ['amy', 'tine']]],
            ['Daouda Sene jox na ñaari junni cash', ['montant' => 10000, 'mode' => 'especes', 'wolof' => true]],
            ['Lamine Gning jox na junni', ['montant' => 5000]],
            ['Serigne Mbacke Kandji fey na 2000 dërëm', ['montant' => 10000]],
            ['Dieynaba Tine dafa jox fukki junni ci OM ref 12345678', ['montant' => 50000, 'mode' => 'orange_money', 'reference' => '12345678']],
            ['Njaay Useynu dafa fey tey', ['date' => '2026-09-24', 'motsNom' => ['njaay', 'useynu'], 'wolof' => true]],
            ['Moussa Juuf fey na weer wi weesu', ['periodes' => ['2026-08'], 'motsNom' => ['moussa', 'juuf']]],
            ['Mor Thiao feyal benn weer démb', ['nbMois' => 1, 'date' => '2026-09-23']],
            ['Bindal Aida Niang, fey na lépp', ['tout' => true, 'motsNom' => ['aida', 'niang']]],
            ['Soxna si Awa Sene dafa fey', ['paiement' => true, 'motsNom' => ['awa', 'sene']]],
            ['Marie Faye mungi fey ñetti weer', ['nbMois' => 3, 'motsNom' => ['marie', 'faye']]],
            ['Aliou Gning fey na septembre ci wave ref WV-9912', ['moisSansAnnee' => [9], 'mode' => 'wave', 'reference' => 'WV-9912']],
            // --- Questions ---
            ['Combien doit Daouda Sene ?', ['question' => true, 'montant' => null, 'motsNom' => ['daouda', 'sene']]],
            ['Est-ce que Amy Tine a payé ce mois-ci ?', ['question' => true, 'periodes' => ['2026-09']]],
            ['Naata la Pape Niama Faye war ?', ['question' => true, 'motsNom' => ['pape', 'niama', 'faye']]],
            ['Qui n\'a pas payé ?', ['impayes' => true, 'motsNom' => []]],
            ['Ñan ñoo feyul ?', ['impayes' => true]],
            ['Bilan du mois', ['bilan' => true]],
            ['Combien avons-nous encaissé ce mois ?', ['bilan' => true, 'periodes' => ['2026-09']]],
            ['naata lañu dajale weer wii', ['bilan' => true, 'periodes' => ['2026-09']]],
            // --- Dialogue ---
            ['oui', ['oui' => true, 'non' => false]],
            ['Waaw', ['oui' => true]],
            ['non', ['non' => true, 'oui' => false]],
            ['deedeet', ['non' => true]],
            ['Bonjour Fatou', ['salutation' => true, 'motsNom' => []]],
            ['Merci Fatou', ['merci' => true, 'motsNom' => []]],
            ['nanga def', ['salutation' => true, 'motsNom' => []]],
            ['le deuxième', ['ordinal' => 2]],
        ];
    }

    #[DataProvider('corpus')]
    public function test_comprehension(string $phrase, array $attendu): void
    {
        $a = (new Analyseur('Fatou', 'NGD-', []))->analyser($phrase, CarbonImmutable::create(2026, 9, 24));

        foreach ($attendu as $champ => $valeur) {
            $this->assertEquals($valeur, $a->{$champ}, "« {$phrase} » → {$champ}");
        }
    }
}
