<?php

namespace Tests\Feature;

use App\Livewire\Paiements\Detail;
use App\Models\User;
use App\Services\PaiementService;
use Livewire\Livewire;
use Tests\TestCase;

/** Contrôle serveur des permissions par rôle (§6, §13) et authentification. */
class SecuriteEtPermissionsTest extends TestCase
{
    public function test_matrice_d_acces_par_role(): void
    {
        $this->aujourdhui('2026-01-10');
        $m = $this->membre();
        $attendus = [
            User::ROLE_ADMIN => ['/membres' => 200, '/paiements/nouveau' => 200, '/utilisateurs' => 200, '/parametres' => 200, '/audit' => 200, '/rapports' => 200],
            User::ROLE_GESTIONNAIRE => ['/membres' => 200, '/paiements/nouveau' => 200, '/utilisateurs' => 403, '/parametres' => 403, '/audit' => 403, '/rapports' => 200],
            User::ROLE_VERIFICATEUR => ['/membres' => 200, '/paiements/nouveau' => 403, '/utilisateurs' => 403, '/parametres' => 403, '/audit' => 200, '/rapports' => 200],
            User::ROLE_MEMBRE => ['/membres' => 403, '/paiements/nouveau' => 403, '/utilisateurs' => 403, '/parametres' => 403, '/audit' => 403, '/rapports' => 403],
        ];
        foreach ($attendus as $role => $routes) {
            $u = $this->utilisateur($role, $role === User::ROLE_MEMBRE ? ['membre_id' => $m->id] : []);
            foreach ($routes as $url => $code) {
                $this->actingAs($u)->get($url)->assertStatus($code);
            }
        }
    }

    public function test_un_membre_ne_voit_que_ses_propres_donnees_et_recus(): void
    {
        $this->aujourdhui('2026-01-10');
        $moi = $this->membre();
        $autre = $this->membre();
        $recu = app(PaiementService::class)->enregistrer([
            'membre_id' => $autre->id, 'periodes' => ['2026-01'], 'montant' => 10000, 'date_paiement' => '2026-01-04',
            'mode_paiement_id' => $this->especes(),
        ]);
        $u = $this->utilisateur(User::ROLE_MEMBRE, ['membre_id' => $moi->id]);

        $this->actingAs($u)->get('/mon-historique')->assertOk();
        $this->actingAs($u)->get("/membres/{$moi->id}")->assertOk();
        $this->actingAs($u)->get("/membres/{$autre->id}")->assertForbidden();
        $this->actingAs($u)->get("/membres/{$autre->id}/historique")->assertForbidden();
        $this->actingAs($u)->get("/recus/{$recu->id}/pdf")->assertForbidden();
    }

    public function test_action_livewire_refusee_cote_serveur_meme_si_appelee_directement(): void
    {
        $this->aujourdhui('2026-01-10');
        $p = app(PaiementService::class)->enregistrer([
            'membre_id' => $this->membre()->id, 'periodes' => ['2026-01'], 'montant' => 10000, 'date_paiement' => '2026-01-04',
            'mode_paiement_id' => $this->especes(),
        ]);
        $tresorier = $this->utilisateur(User::ROLE_GESTIONNAIRE);

        Livewire::actingAs($tresorier)->test(Detail::class, ['paiement' => $p])
            ->call('ouvrir', 'annuler')
            ->assertForbidden();
        $this->assertSame('valide', $p->fresh()->statut);
    }

    public function test_connexion_par_identifiant_email_ou_telephone_et_limitation(): void
    {
        $u = $this->utilisateur(User::ROLE_GESTIONNAIRE, ['identifiant' => 'caissier', 'email' => 'c@exemple.sn', 'telephone' => '771234567']);

        foreach (['caissier', 'c@exemple.sn', '77 123 45 67'] as $id) {
            Livewire::test(\App\Livewire\Auth\Connexion::class)->set('identifiant', $id)->set('password', 'Secret123')
                ->call('connecter')->assertHasNoErrors()->assertRedirect();
            auth()->logout();
        }

        $c = Livewire::test(\App\Livewire\Auth\Connexion::class)->set('identifiant', 'caissier');
        foreach (range(1, 5) as $i) {
            $c->set('password', 'faux')->call('connecter');
        }
        $c->set('password', 'Secret123')->call('connecter')->assertHasErrors('identifiant');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.blocage']);
    }

    public function test_compte_desactive_ne_peut_pas_se_connecter_ni_rester_connecte(): void
    {
        $u = $this->utilisateur(User::ROLE_GESTIONNAIRE, ['identifiant' => 'parti']);
        $u->update(['actif' => false]);

        Livewire::test(\App\Livewire\Auth\Connexion::class)->set('identifiant', 'parti')->set('password', 'Secret123')
            ->call('connecter')->assertHasErrors('identifiant');
        $this->actingAs($u)->get('/')->assertRedirect(route('login'));
    }

    public function test_mot_de_passe_temporaire_impose_un_changement(): void
    {
        $u = $this->utilisateur(User::ROLE_GESTIONNAIRE, ['doit_changer_mdp' => true]);
        $this->actingAs($u)->get('/membres')->assertRedirect(route('password.change'));
    }

    public function test_en_tetes_de_securite(): void
    {
        $this->get('/connexion')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_nouveaux_mots_de_passe_a_la_mise_en_ligne(): void
    {
        $actif = $this->utilisateur(User::ROLE_ADMIN, ['identifiant' => 'admin', 'password' => 'Admin@2026', 'doit_changer_mdp' => false]);
        $inactif = $this->utilisateur(User::ROLE_GESTIONNAIRE, ['identifiant' => 'ancien', 'password' => 'Ancien@2026', 'actif' => false]);

        $this->artisan('coop:nouveaux-mots-de-passe', ['--force' => true])->assertSuccessful();

        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('Admin@2026', $actif->fresh()->password));
        $this->assertTrue($actif->fresh()->doit_changer_mdp);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Ancien@2026', $inactif->fresh()->password), 'Compte désactivé inchangé');
        $this->assertDatabaseHas('audit_logs', ['action' => 'utilisateur.reinitialiser_mdp']);
    }
}
