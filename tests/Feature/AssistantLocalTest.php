<?php

namespace Tests\Feature;

use App\Livewire\Assistant;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\User;
use App\Services\Assistant\AssistantIA;
use App\Services\Assistant\Local\AssistantLocal;
use App\Services\CotisationService;
use App\Services\ImportMembres;
use App\Services\Parametres;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Dialogues complets avec l'assistante locale, sur la vraie liste du personnel.
 * Situation : 24/09/2026, tous les membres doivent août et septembre (10 000 FCFA chacun).
 */
class AssistantLocalTest extends TestCase
{
    private User $tresorier;
    private array $ctx = [];
    private ?string $proposition = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aujourdhui('2026-09-24 10:00:00');
        app(Parametres::class)->set('cotisation_periode_debut', '2026-08');
        $import = app(ImportMembres::class);
        $import->importer($import->lire(database_path('seeders/data/liste_personnel.docx')), '2026-08-01');
        app(CotisationService::class)->genererJusqua();
        $this->tresorier = $this->utilisateur(User::ROLE_GESTIONNAIRE);
        $this->actingAs($this->tresorier);
    }

    /** Envoie une phrase à Fatou en conservant le contexte, comme l'écran. */
    private function dire(string $phrase, ?User $user = null): array
    {
        $r = app(AssistantLocal::class)->traiter($user ?? $this->tresorier, $this->ctx, $phrase, $this->proposition !== null);
        $this->ctx = $r['contexte'];
        if ($r['action'] === null) {
            $this->proposition = $r['proposition_id'];
        }

        return $r;
    }

    private function proposition(): array
    {
        $p = app(AssistantIA::class)->proposition($this->tresorier, $this->proposition);
        $this->assertNotNull($p, 'Une proposition d\'encaissement était attendue.');

        return $p;
    }

    private function matricule(string $m): int
    {
        return Membre::where('matricule', $m)->value('id');
    }

    public function test_encaissement_simple_de_amy_tine(): void
    {
        $r = $this->dire('Fatou, fais un encaissement de Amy Tine aujourd\'hui');
        $p = $this->proposition();

        $this->assertSame('Amy TINE', $p['membre']);
        $this->assertSame(['2026-08'], $p['periodes'], 'Le mois dû le plus ancien par défaut');
        $this->assertSame(10000, $p['montant']);
        $this->assertSame('Espèces', $p['mode']);
        $this->assertSame('2026-09-24', $p['date_paiement']);
        $this->assertStringContainsString('Confirmer', $r['reponse']);

        $this->assertSame('confirmer', $this->dire('oui')['action']);
    }

    public function test_wolof_avec_reference_wave_demandee(): void
    {
        $r = $this->dire('Amy Tine dafa fey ñaari weer tey ci Wave');
        $this->assertNull($this->proposition);
        $this->assertSame('reference', $this->ctx['attente']);
        $this->assertStringContainsString('soxla naa', $r['reponse'], 'Réponse en wolof');

        $this->dire('58213');
        $p = $this->proposition();
        $this->assertSame(['2026-08', '2026-09'], $p['periodes']);
        $this->assertSame(20000, $p['montant']);
        $this->assertSame('Wave', $p['mode']);
        $this->assertSame('58213', $p['reference']);
    }

    public function test_homonymes_choix_par_numero(): void
    {
        $r = $this->dire('Encaisse Daouda Sene 10000');
        $this->assertSame('membre', $this->ctx['attente']);
        $this->assertCount(3, $this->ctx['candidats']);
        $this->assertStringContainsString('Lequel', $r['reponse']);

        $deuxieme = $this->ctx['candidats'][1];
        $this->dire('2');
        $p = $this->proposition();
        $this->assertSame($deuxieme, $p['membre_id']);
        $this->assertSame(10000, $p['montant']);
    }

    public function test_homonymes_choix_par_matricule(): void
    {
        $this->dire('Modou Sene a payé');
        $this->assertSame('membre', $this->ctx['attente']);
        $this->assertContains($this->matricule('NGD-004'), $this->ctx['candidats'], 'El Hadji Modou SENE fait partie des candidats');

        $this->dire('NGD-058');
        $this->assertSame($this->matricule('NGD-058'), $this->proposition()['membre_id']);
    }

    public function test_question_puis_oui_encaisse_tout(): void
    {
        $r = $this->dire('Combien doit Amy Tine ?');
        $this->assertNull($this->proposition);
        $this->assertStringContainsString('20 000', str_replace("\u{202F}", ' ', $r['reponse']));

        $this->dire('oui');
        $p = $this->proposition();
        $this->assertSame(['2026-08', '2026-09'], $p['periodes']);
        $this->assertSame(20000, $p['montant']);
    }

    public function test_correction_d_une_proposition(): void
    {
        $this->dire('Encaisse Amy Tine');
        $this->assertSame(['2026-08'], $this->proposition()['periodes']);

        $this->dire('non, plutôt 2 mois par wave réf 777');
        $p = $this->proposition();
        $this->assertSame(['2026-08', '2026-09'], $p['periodes']);
        $this->assertSame('Wave', $p['mode']);
        $this->assertSame('777', $p['reference']);
    }

    public function test_orthographe_wolof_des_noms(): void
    {
        $this->dire('Njaay Useynu dafa fey tey');
        $this->assertSame($this->matricule('NGD-022'), $this->proposition()['membre_id'], 'Ousseynou NDIAYE');

        $this->ctx = [];
        $this->proposition = null;
        $this->dire('Moussa Juuf fey na weer wi weesu');
        $p = $this->proposition();
        $this->assertSame($this->matricule('NGD-037'), $p['membre_id'], 'Moussa DIOUF');
        $this->assertSame(['2026-08'], $p['periodes']);
    }

    public function test_montant_wolof_en_derem_et_paiement_partiel(): void
    {
        $this->dire('Bineta Pouye jox na junni');
        $p = $this->proposition();
        $this->assertSame(5000, $p['montant'], '« junni » = 1 000 dërëm = 5 000 FCFA');
        $this->assertSame(['2026-08'], $p['periodes']);
    }

    public function test_impayes_bilan_et_introuvable(): void
    {
        $this->assertStringContainsString('en impayé', $this->dire('Qui n\'a pas payé ?')['reponse']);
        $this->assertStringContainsString('Septembre 2026', $this->dire('Bilan du mois')['reponse']);

        $r = $this->dire('Encaisse Xyzzy Qwerty 5000');
        $this->assertFalse($r['compris']);
        $this->assertNull($this->proposition);
    }

    public function test_mois_deja_paye_explique_et_propose_le_suivant(): void
    {
        $this->dire('Encaisse Amy Tine août et septembre');
        $this->assertSame('confirmer', $this->dire('oui')['action']);
        app(AssistantIA::class)->confirmer($this->tresorier, $this->proposition, false);
        [$this->ctx, $this->proposition] = [[], null];

        $r = $this->dire('Aliou Gning fey na septembre'); // autre membre : septembre dû
        $this->assertSame(['2026-09'], $this->proposition()['periodes'], 'Septembre 2026, pas 2027');

        [$this->ctx, $this->proposition] = [[], null];
        $r = $this->dire('Amy Tine a payé septembre');
        $this->assertNull($this->proposition);
        $this->assertStringContainsString('déjà payé Septembre 2026', $r['reponse']);
        $this->assertStringContainsString('Octobre 2026', $r['reponse']);
        $this->dire('oui');
        $this->assertSame(['2026-10'], $this->proposition()['periodes']);
    }

    public function test_desambiguisation_fine_des_noms(): void
    {
        $this->dire('Ibra Sarr 10000');
        $this->assertSame($this->matricule('NGD-086'), $this->proposition()['membre_id'], '« Ibra IBRA » (N° 28) n\'est pas confondu');

        [$this->ctx, $this->proposition] = [[], null];
        $this->dire('mame diarra 10000');
        $this->assertEqualsCanonicalizing([$this->matricule('NGD-051'), $this->matricule('NGD-087')], $this->ctx['candidats'], 'Barra NDIAYE écarté');

        [$this->ctx, $this->proposition] = [[], null];
        $r = $this->dire('Babacar Dione ak Ibra Sarr fey nañu');
        $this->assertStringContainsString('un encaissement à la fois', $r['reponse']);
    }

    public function test_le_verificateur_ne_peut_pas_faire_encaisser(): void
    {
        $verif = $this->utilisateur(User::ROLE_VERIFICATEUR);
        $r = $this->dire('Encaisse Amy Tine 10000', $verif);
        $this->assertNull($r['proposition_id']);
        $this->assertStringContainsString('Amy TINE', $r['reponse']);
    }

    public function test_ecran_complet_du_message_au_recu(): void
    {
        Livewire::test(Assistant::class)
            ->set('saisie', 'Fatou, encaisse Amy Tine 10000 cash aujourd\'hui')
            ->call('envoyer')
            ->assertSee('Encaissement à confirmer')
            ->assertSee('Amy TINE')
            ->set('saisie', 'waaw')
            ->call('envoyer')
            ->assertSee('C\'est enregistré');

        $p = Paiement::first();
        $this->assertSame(10000, $p->montant);
        $this->assertSame($this->tresorier->id, $p->enregistre_par);
        $this->assertStringContainsString('Saisi via l\'assistante Fatou', $p->note);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ia.commande']);
    }
}
