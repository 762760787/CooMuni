<?php

namespace Tests;

use App\Models\Membre;
use App\Models\ModePaiement;
use App\Models\User;
use App\Services\MembreService;
use App\Services\Parametres;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferentielsSeeder;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentielsSeeder::class, RolesPermissionsSeeder::class]);
        app(Parametres::class)->set('cotisation_periode_debut', '2026-01');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /** Fige la date du jour. */
    protected function aujourdhui(string $date): void
    {
        Carbon::setTestNow($date);
        CarbonImmutable::setTestNow($date);
    }

    protected function utilisateur(string $role, array $attributs = []): User
    {
        $u = User::create($attributs + [
            'name' => $role.' test',
            'identifiant' => strtolower(str_replace(['é', ' '], ['e', '.'], $role)).'.'.uniqid(),
            'password' => 'Secret123',
            'actif' => true,
        ]);
        $u->assignRole($role);

        return $u;
    }

    protected function membre(array $attributs = []): Membre
    {
        $this->actingAs($this->admin ??= $this->utilisateur(User::ROLE_ADMIN));

        return app(MembreService::class)->creer($attributs + [
            'matricule' => 'T-'.uniqid(),
            'nom' => 'TEST',
            'prenom' => 'Membre '.uniqid(),
            'date_adhesion' => '2026-01-01',
        ]);
    }

    protected ?User $admin = null;

    protected function especes(): int
    {
        return ModePaiement::where('code', 'especes')->value('id');
    }
}
