<?php

namespace Tests\Feature;

use App\Livewire\Cotisations\Mensuelle;
use App\Livewire\Membres\Formulaire;
use App\Livewire\Paiements\Enregistrer;
use App\Models\Cotisation;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

/** Parcours utilisateurs §9.1 et critères d'acceptation §27.1 / §27.2, via les composants réels. */
class EcransLivewireTest extends TestCase
{
    public function test_ajout_membre_bloque_les_doublons_et_genere_la_cotisation_du_mois(): void
    {
        $this->aujourdhui('2026-03-03');
        $tresorier = $this->utilisateur(User::ROLE_GESTIONNAIRE);
        $existant = $this->membre(['telephone' => '770000001']);

        Livewire::actingAs($tresorier)->test(Formulaire::class)
            ->set('nom', 'Diop')->set('prenom', 'Awa')->set('telephone', '77 000 00 01')
            ->call('enregistrer')
            ->assertHasErrors('telephone');

        Livewire::actingAs($tresorier)->test(Formulaire::class)
            ->set('nom', 'Diop')->set('prenom', 'Awa')->set('telephone', '77 000 00 02')->set('date_adhesion', '2026-03-03')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertRedirect();

        $m = Membre::where('nom', 'DIOP')->first();
        $this->assertNotNull($m);
        $this->assertTrue(Cotisation::where('membre_id', $m->id)->where('periode', '2026-03')->exists(), 'Adhésion le 3 : mars dû');
        $this->assertNotEquals($existant->id, $m->id);
    }

    public function test_enregistrement_d_un_paiement_par_le_tresorier(): void
    {
        $this->aujourdhui('2026-02-04');
        $m = $this->membre();
        $tresorier = $this->utilisateur(User::ROLE_GESTIONNAIRE);

        Livewire::actingAs($tresorier)->test(Enregistrer::class, ['membreId' => $m->id])
            ->assertSet('periodes', ['2026-01'])
            ->set('periodes', ['2026-01', '2026-02'])
            ->assertSet('montant', '20000')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertRedirect();

        $p = Paiement::first();
        $this->assertSame($tresorier->id, $p->enregistre_par);
        $this->assertSame(['paye_retard', 'paye'], Cotisation::where('membre_id', $m->id)->orderBy('periode')->pluck('statut')->map->value->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'paiement.creer', 'cible_id' => $p->id]);
    }

    public function test_saisie_rapide_depuis_les_cotisations_du_mois(): void
    {
        $this->aujourdhui('2026-01-05');
        $m = $this->membre();
        $c = Cotisation::where('membre_id', $m->id)->first();

        Livewire::actingAs($this->utilisateur(User::ROLE_GESTIONNAIRE))->test(Mensuelle::class)
            ->call('ouvrirSaisie', $c->id)
            ->assertSet('montant', '10000')
            ->call('encaisser')
            ->assertHasNoErrors();

        $this->assertSame('paye', $c->fresh()->statut->value);
    }

    public function test_le_verificateur_ne_peut_pas_encaisser(): void
    {
        $this->aujourdhui('2026-01-05');
        $c = Cotisation::where('membre_id', $this->membre()->id)->first();

        Livewire::actingAs($this->utilisateur(User::ROLE_VERIFICATEUR))->test(Mensuelle::class)
            ->call('ouvrirSaisie', $c->id)
            ->assertForbidden();
    }
}
