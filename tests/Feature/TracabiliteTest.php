<?php

namespace Tests\Feature;

use App\Enums\StatutCotisation;
use App\Exceptions\RegleMetierException;
use App\Models\AuditLog;
use App\Models\Cotisation;
use App\Models\Paiement;
use App\Models\User;
use App\Services\PaiementService;
use LogicException;
use Tests\TestCase;

/** Non-suppression, annulation tracée, doublons, numérotation (§8.1, §8.2, §13). */
class TracabiliteTest extends TestCase
{
    private function paiement(int $membreId, int $montant = 10000, ?string $reference = null): Paiement
    {
        return app(PaiementService::class)->enregistrer([
            'membre_id' => $membreId, 'periodes' => ['2026-01'], 'montant' => $montant, 'date_paiement' => '2026-01-04',
            'mode_paiement_id' => $this->especes(), 'reference' => $reference, 'confirmer_doublon' => true,
        ]);
    }

    public function test_aucune_suppression_possible_des_donnees_financieres_et_de_l_audit(): void
    {
        $this->aujourdhui('2026-01-10');
        $p = $this->paiement($this->membre()->id);

        foreach ([$p, $p->imputations->first(), Cotisation::first(), $p->membre, AuditLog::first()] as $modele) {
            try {
                $modele->delete();
                $this->fail(class_basename($modele).' ne doit pas pouvoir être supprimé');
            } catch (LogicException) {
                $this->assertTrue($modele->exists);
            }
        }
    }

    public function test_workflow_annulation_demande_puis_validation_recalcule_la_cotisation(): void
    {
        $this->aujourdhui('2026-01-10');
        $m = $this->membre();
        $tresorier = $this->utilisateur(User::ROLE_GESTIONNAIRE);
        $this->actingAs($tresorier);
        $p = $this->paiement($m->id);

        app(PaiementService::class)->demanderAnnulation($p, 'Mauvais membre sélectionné');
        $this->assertTrue($p->fresh()->annulationEnAttente());
        $this->assertSame(1, Paiement::enAttenteAnnulation()->count());

        $this->actingAs($this->admin);
        app(PaiementService::class)->annuler($p->fresh(), 'Validation de la demande');

        $p->refresh();
        $this->assertSame('annule', $p->statut);
        $this->assertSame($this->admin->id, $p->annule_par);
        $this->assertSame(StatutCotisation::APayer, Cotisation::where('membre_id', $m->id)->where('periode', '2026-01')->first()->statut);
        $this->assertSame(['paiement.creer', 'paiement.demande_annulation', 'paiement.annuler'],
            AuditLog::where('cible_type', 'Paiement')->where('cible_id', $p->id)->orderBy('id')->pluck('action')->all());
    }

    public function test_reference_de_transaction_deja_utilisee_bloquee(): void
    {
        $this->aujourdhui('2026-01-10');
        $this->paiement($this->membre()->id, 10000, 'WAVE-123');
        $this->expectException(RegleMetierException::class);
        $this->paiement($this->membre()->id, 10000, 'WAVE-123');
    }

    public function test_doublon_probable_exige_une_confirmation(): void
    {
        $this->aujourdhui('2026-02-10');
        $m = $this->membre();
        $this->paiement($m->id, 5000);

        $this->expectException(RegleMetierException::class);
        app(PaiementService::class)->enregistrer([
            'membre_id' => $m->id, 'periodes' => ['2026-01'], 'montant' => 5000, 'date_paiement' => '2026-01-05',
            'mode_paiement_id' => $this->especes(),
        ]);
    }

    public function test_numeros_de_recu_sequentiels(): void
    {
        $this->aujourdhui('2026-03-10');
        $m = $this->membre();
        $a = $this->paiement($m->id);
        $b = app(PaiementService::class)->enregistrer([
            'membre_id' => $m->id, 'periodes' => ['2026-02'], 'montant' => 10000, 'date_paiement' => '2026-02-04',
            'mode_paiement_id' => $this->especes(), 'confirmer_doublon' => true,
        ]);
        $this->assertSame('REC-2026-00001', $a->numero_recu);
        $this->assertSame('REC-2026-00002', $b->numero_recu);
    }
}
