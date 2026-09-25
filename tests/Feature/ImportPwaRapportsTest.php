<?php

namespace Tests\Feature;

use App\Models\Membre;
use App\Models\User;
use App\Services\ImportMembres;
use App\Services\Rapports;
use Tests\TestCase;

class ImportPwaRapportsTest extends TestCase
{
    public function test_import_de_la_liste_du_personnel_idempotent(): void
    {
        $import = app(ImportMembres::class);
        $lignes = $import->lire(database_path('seeders/data/liste_personnel.docx'));

        $this->assertCount(82, $lignes);
        $r = $import->importer($lignes, '2026-01-01');
        $this->assertSame(82, $r['crees']);
        $this->assertNotEmpty(array_filter($r['avertissements'], fn ($a) => str_contains($a, 'DAOUDA SENE')));
        $this->assertSame('NGD-001', Membre::where('nom', 'TINE')->where('prenom', 'Isma')->value('matricule'));

        $this->assertSame(0, $import->importer($lignes, '2026-01-01')['crees']);
        $this->assertSame(82, Membre::count());
    }

    public function test_manifeste_pwa_et_service_worker(): void
    {
        $this->get('/manifest.webmanifest')->assertOk()
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('start_url', '/?source=pwa')
            ->assertJsonFragment(['sizes' => '192x192'])
            ->assertJsonFragment(['sizes' => '512x512']);
        $this->assertFileExists(public_path('sw.js'));
        $this->get('/hors-ligne')->assertOk()->assertSee('hors connexion');
    }

    public function test_tous_les_rapports_se_generent_et_s_exportent(): void
    {
        $this->aujourdhui('2026-02-10');
        $m = $this->membre();
        $admin = $this->admin;
        $params = ['periode' => '2026-02', 'annee' => 2026, 'du' => '2026-01-01', 'au' => '2026-02-10', 'membre_id' => $m->id];

        foreach (array_keys(Rapports::TYPES) as $type) {
            $r = app(Rapports::class)->generer($type, $params);
            $this->assertNotEmpty($r['titre'], $type);
            foreach (['pdf', 'xlsx', 'csv'] as $format) {
                $this->actingAs($admin)->get('/rapports/export?'.http_build_query($params + ['type' => $type, 'format' => $format]))->assertOk();
            }
        }
        $this->actingAs($this->utilisateur(User::ROLE_MEMBRE, ['membre_id' => $m->id]))
            ->get('/rapports/export?type=etat_mensuel&periode=2026-02&format=pdf')->assertForbidden();
    }
}
