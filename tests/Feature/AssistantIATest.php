<?php

namespace Tests\Feature;

use App\Exceptions\RegleMetierException;
use App\Models\Paiement;
use App\Models\User;
use App\Services\Assistant\AssistantIA;
use App\Services\Assistant\OutilsAssistant;
use Tests\TestCase;

/** Outils de l'assistante « Fatou » : lecture, préparation, confirmation — sans appel réseau. */
class AssistantIATest extends TestCase
{
    private function outils(): OutilsAssistant
    {
        return app(OutilsAssistant::class);
    }

    public function test_recherche_exacte_puis_approchante(): void
    {
        $this->aujourdhui('2026-09-24');
        $amy = $this->membre(['nom' => 'TINE', 'prenom' => 'Amy']);
        $this->membre(['nom' => 'TINE', 'prenom' => 'Fatou']);
        $tresorier = $this->utilisateur(User::ROLE_GESTIONNAIRE);

        $r = $this->outils()->rechercherMembre($tresorier, 'amy tine');
        $this->assertSame([$amy->id], collect($r['correspondances_exactes'])->pluck('membre_id')->all());

        // Faute de frappe sur le prénom : aucune correspondance exacte, les TINE sont proposés.
        $r = $this->outils()->rechercherMembre($tresorier, 'Ami Tine');
        $this->assertEmpty($r['correspondances_exactes']);
        $this->assertCount(2, $r['correspondances_approchantes']);
    }

    public function test_proposition_puis_confirmation_cree_le_paiement_trace(): void
    {
        $this->aujourdhui('2026-09-24');
        $m = $this->membre(['nom' => 'TINE', 'prenom' => 'Amy', 'date_adhesion' => '2026-09-01']);
        $tresorier = $this->utilisateur(User::ROLE_GESTIONNAIRE);
        $outils = $this->outils();

        [$json, $erreur] = $outils->executer($tresorier, 'proposer_encaissement', [
            'membre_id' => $m->id, 'periodes' => ['2026-09'], 'montant' => 10000,
            'mode_paiement' => 'especes', 'date_paiement' => '2026-09-24', 'reference' => null,
        ], 'Fatou, encaisse Amy Tine aujourd\'hui');

        $this->assertFalse($erreur, $json);
        $this->assertSame('en_attente_de_confirmation', json_decode($json, true)['statut']);
        $this->assertSame(0, Paiement::count(), 'Rien n\'est enregistré avant confirmation');

        $this->actingAs($tresorier);
        $paiement = app(AssistantIA::class)->confirmer($tresorier, $outils->propositionId, false);

        $this->assertSame(10000, $paiement->montant);
        $this->assertSame($tresorier->id, $paiement->enregistre_par);
        $this->assertStringContainsString('assistante Fatou', $paiement->note);
        $this->assertDatabaseHas('audit_logs', ['action' => 'paiement.creer', 'cible_id' => $paiement->id]);

        // Une proposition ne peut servir qu'une fois.
        $this->expectException(RegleMetierException::class);
        app(AssistantIA::class)->confirmer($tresorier, $outils->propositionId, false);
    }

    public function test_les_erreurs_metier_sont_renvoyees_a_l_ia(): void
    {
        $this->aujourdhui('2026-09-24');
        $m = $this->membre(['date_adhesion' => '2026-09-01']);
        $tresorier = $this->utilisateur(User::ROLE_GESTIONNAIRE);
        $base = ['membre_id' => $m->id, 'periodes' => ['2026-09'], 'montant' => 10000, 'mode_paiement' => 'especes', 'date_paiement' => '2026-09-24', 'reference' => null];

        foreach ([
            ['montant' => 50000],                          // supérieur au dû
            ['mode_paiement' => 'wave'],                   // référence Wave obligatoire
            ['date_paiement' => '2026-12-01'],             // date future
            ['periodes' => ['2026-03']],                   // période non due
        ] as $variation) {
            [$msg, $erreur] = $this->outils()->executer($tresorier, 'proposer_encaissement', $variation + $base, 'test');
            $this->assertTrue($erreur, json_encode($variation));
        }
        $this->assertSame(0, Paiement::count());
    }

    public function test_permissions_des_outils_et_de_la_confirmation(): void
    {
        $this->aujourdhui('2026-09-24');
        $m = $this->membre(['date_adhesion' => '2026-09-01']);
        $tresorier = $this->utilisateur(User::ROLE_GESTIONNAIRE);
        $verificateur = $this->utilisateur(User::ROLE_VERIFICATEUR);
        $membreUser = $this->utilisateur(User::ROLE_MEMBRE, ['membre_id' => $m->id]);

        $this->assertNotContains('proposer_encaissement', array_column($this->outils()->definitions($verificateur), 'name'));
        $this->assertSame([], $this->outils()->definitions($membreUser));
        [, $erreur] = $this->outils()->executer($verificateur, 'proposer_encaissement', ['membre_id' => $m->id], 'x');
        $this->assertTrue($erreur);

        $outils = $this->outils();
        $outils->executer($tresorier, 'proposer_encaissement', [
            'membre_id' => $m->id, 'periodes' => ['2026-09'], 'montant' => 10000,
            'mode_paiement' => 'especes', 'date_paiement' => '2026-09-24', 'reference' => null,
        ], 'x');
        // Un autre utilisateur ne peut pas confirmer la proposition du trésorier.
        $this->assertNull(app(AssistantIA::class)->proposition($this->admin, $outils->propositionId));

        $this->actingAs($membreUser)->get('/assistant')->assertForbidden();
        $this->actingAs($verificateur)->get('/assistant')->assertForbidden();
        $this->actingAs($tresorier)->get('/assistant')->assertOk()->assertSee('Fatou');
    }

    public function test_instructions_contiennent_le_nom_les_regles_et_les_modes(): void
    {
        $txt = app(AssistantIA::class)->instructions();
        $this->assertStringContainsString('Tu es Fatou', $txt);
        $this->assertStringContainsString('le 5 de chaque mois', $txt);
        $this->assertStringContainsString('orange_money', $txt);
        $this->assertStringContainsString('dërëm', $txt);
    }
}
