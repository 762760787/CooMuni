<?php

namespace Tests\Feature;

use App\Enums\StatutCotisation;
use App\Enums\StatutMembre;
use App\Exceptions\RegleMetierException;
use App\Models\Cotisation;
use App\Services\CotisationService;
use App\Services\MembreService;
use App\Services\PaiementService;
use App\Services\Parametres;
use App\Support\Periode;
use Tests\TestCase;

/** Règles métier des cotisations (§8.1) et décisions documentées dans ASSUMPTIONS.md (§8.2). */
class ReglesCotisationTest extends TestCase
{
    private function payer(int $membreId, array $periodes, int $montant, string $date, array $extra = [])
    {
        return app(PaiementService::class)->enregistrer($extra + [
            'membre_id' => $membreId, 'periodes' => $periodes, 'montant' => $montant,
            'date_paiement' => $date, 'mode_paiement_id' => $this->especes(), 'confirmer_doublon' => true,
        ]);
    }

    public function test_generation_mensuelle_idempotente_au_montant_parametre(): void
    {
        $this->aujourdhui('2026-03-10');
        $m = $this->membre();
        $service = app(CotisationService::class);

        $this->assertSame(3, Cotisation::where('membre_id', $m->id)->count(), 'Janvier à mars générés à la création');
        $this->assertSame(0, $service->genererPeriode(Periode::fromString('2026-03')));
        $c = Cotisation::where('membre_id', $m->id)->where('periode', '2026-03')->first();
        $this->assertSame(10000, $c->montant_attendu);
        $this->assertSame('2026-03-05', $c->date_echeance->toDateString());
    }

    public function test_paiement_au_plus_tard_le_5_est_a_temps_et_apres_le_5_en_retard(): void
    {
        $this->aujourdhui('2026-02-20');
        $m = $this->membre();

        $this->payer($m->id, ['2026-01'], 10000, '2026-01-05');
        $this->payer($m->id, ['2026-02'], 10000, '2026-02-06');

        $this->assertSame(StatutCotisation::Paye, Cotisation::where('membre_id', $m->id)->where('periode', '2026-01')->first()->statut);
        $this->assertSame(StatutCotisation::PayeRetard, Cotisation::where('membre_id', $m->id)->where('periode', '2026-02')->first()->statut);
    }

    public function test_non_regle_apres_echeance_est_impaye_et_avant_a_payer(): void
    {
        $this->aujourdhui('2026-03-04');
        $m = $this->membre();

        $fev = Cotisation::where('membre_id', $m->id)->where('periode', '2026-02')->first();
        $mars = Cotisation::where('membre_id', $m->id)->where('periode', '2026-03')->first();
        $this->assertSame('impaye', $fev->etat()->value);
        $this->assertSame('a_venir', $mars->etat()->value);
        $this->assertSame(2, Cotisation::impayees()->where('membre_id', $m->id)->count());
    }

    public function test_paiement_groupe_ventile_du_plus_ancien_au_plus_recent_et_partiel(): void
    {
        $this->aujourdhui('2026-03-02');
        $m = $this->membre();

        $p = $this->payer($m->id, ['2026-03', '2026-01', '2026-02'], 25000, '2026-03-02');

        $this->assertSame([10000, 10000, 5000], $p->cotisations->sortBy('periode')->pluck('pivot.montant')->map(fn ($v) => (int) $v)->values()->all());
        $this->assertSame(StatutCotisation::Partiel, Cotisation::where('membre_id', $m->id)->where('periode', '2026-03')->first()->statut);
    }

    public function test_montant_superieur_au_du_refuse(): void
    {
        $this->aujourdhui('2026-01-10');
        $m = $this->membre();
        $this->expectException(RegleMetierException::class);
        $this->payer($m->id, ['2026-01'], 15000, '2026-01-10');
    }

    public function test_paiement_partiel_refuse_si_parametre_desactive(): void
    {
        $this->aujourdhui('2026-01-10');
        $m = $this->membre();
        app(Parametres::class)->set('paiement_partiel_autorise', false);
        $this->expectException(RegleMetierException::class);
        $this->payer($m->id, ['2026-01'], 5000, '2026-01-10');
    }

    public function test_adhesion_en_cours_de_mois_selon_echeance(): void
    {
        $this->aujourdhui('2026-04-20');
        $avant = $this->membre(['date_adhesion' => '2026-04-05']);
        $apres = $this->membre(['date_adhesion' => '2026-04-12']);

        $this->assertTrue(Cotisation::where('membre_id', $avant->id)->where('periode', '2026-04')->exists());
        $this->assertFalse(Cotisation::where('membre_id', $apres->id)->where('periode', '2026-04')->exists());
        $this->assertSame('2026-05', (string) app(CotisationService::class)->premierePeriodeDue($apres));
    }

    public function test_changement_de_montant_non_retroactif(): void
    {
        $this->aujourdhui('2026-02-10');
        $m = $this->membre();
        app(Parametres::class)->set('cotisation_montant', 12000);
        $this->aujourdhui('2026-03-01');
        app(CotisationService::class)->genererJusqua();

        $montants = Cotisation::where('membre_id', $m->id)->orderBy('periode')->pluck('montant_attendu', 'periode')->all();
        $this->assertSame(['2026-01' => 10000, '2026-02' => 10000, '2026-03' => 12000], $montants);
    }

    public function test_sortie_annule_les_cotisations_posterieures_non_payees(): void
    {
        $this->aujourdhui('2026-04-01');
        $m = $this->membre();
        $this->payer($m->id, ['2026-01'], 10000, '2026-01-03');

        app(MembreService::class)->changerStatut($m, StatutMembre::Sorti, '2026-02-03', 'Mutation hors commune');

        $statuts = Cotisation::where('membre_id', $m->id)->orderBy('periode')->pluck('statut', 'periode')->map->value->all();
        // Sortie le 3 février (avant l'échéance du 5) : février n'est plus dû, janvier reste payé.
        $this->assertSame(['2026-01' => 'paye', '2026-02' => 'annule', '2026-03' => 'annule', '2026-04' => 'annule'], $statuts);
    }

    public function test_membre_suspendu_non_redevable_si_parametre_desactive(): void
    {
        $this->aujourdhui('2026-01-15');
        $m = $this->membre();
        app(Parametres::class)->set('suspendu_redevable', false);
        app(MembreService::class)->changerStatut($m, StatutMembre::Suspendu, '2026-01-15', 'Mise en disponibilité');

        $this->aujourdhui('2026-02-02');
        app(CotisationService::class)->genererJusqua();
        $this->assertFalse(Cotisation::where('membre_id', $m->id)->where('periode', '2026-02')->exists());
    }

    public function test_regularisation_et_annulation_exigent_un_motif_et_sont_tracees(): void
    {
        $this->aujourdhui('2026-01-20');
        $m = $this->membre();
        $c = Cotisation::where('membre_id', $m->id)->first();

        try {
            app(CotisationService::class)->regulariser($c, '');
            $this->fail('Motif obligatoire');
        } catch (RegleMetierException) {
        }
        app(CotisationService::class)->regulariser($c, 'Exonération décidée par le bureau');
        $this->assertSame(StatutCotisation::Regularise, $c->fresh()->statut);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cotisation.regulariser', 'cible_id' => $c->id]);
    }
}
