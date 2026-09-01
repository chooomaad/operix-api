<?php

namespace Tests\Feature\Api;

use App\Models\Contractor;
use App\Models\ContractorEmployee;
use App\Models\Employee;
use App\Models\Intern;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recherche unifiée « personnes » (endpoint /people/search) : multi-type,
 * par matricule/nom/référence/entreprise, sans confusion entre homonymes de
 * types différents, et strictement isolée par tenant.
 */
class PeopleSearchTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Tenant $t): User
    {
        return User::factory()->create(['tenant_id' => $t->id, 'role' => 'company_admin', 'is_active' => true]);
    }

    private function inTenant(Tenant $t, callable $fn)
    {
        return app(TenantContext::class)->runWithoutScope(function () use ($t, $fn) {
            app(TenantContext::class)->set($t->id);
            try { return $fn(); } finally { app(TenantContext::class)->clear(); }
        });
    }

    /** @return array<int,array{type:string,id:int,identifier:string}> */
    private function search(User $admin, string $q, ?string $type = null): array
    {
        $url = '/api/v1/people/search?q=' . urlencode($q) . ($type ? "&type={$type}" : '');
        return collect($this->actingAs($admin)->getJson($url)->assertOk()->json('data'))
            ->map(fn ($p) => ['type' => $p['type'], 'id' => $p['id'], 'identifier' => $p['identifier']])->all();
    }

    public function test_recherche_multi_type_et_homonymes(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = $this->admin($t);

        [$emp, $con, $vis, $int] = $this->inTenant($t, function () use ($t) {
            $emp = Employee::factory()->create(['tenant_id' => $t->id, 'nom' => 'Ahmed', 'prenom' => 'Mohamed', 'matricule' => '100289']);
            $c   = Contractor::create(['tenant_id' => $t->id, 'company_name' => 'ABC Construction', 'activite' => 'BTP', 'status' => 'active']);
            $con = ContractorEmployee::create(['tenant_id' => $t->id, 'contractor_id' => $c->id, 'nom' => 'Ahmed', 'prenom' => 'Mohamed', 'badge_number' => 'CONT-012', 'is_active' => true]);
            $vis = Visitor::create(['tenant_id' => $t->id, 'nom' => 'Ahmed', 'prenom' => 'Mohamed', 'badge_number' => 'VIS-2026-044', 'motif' => 'R', 'personne_visitee' => 'D', 'status' => 'in', 'checked_in_at' => now()]);
            $int = Intern::create(['tenant_id' => $t->id, 'nom' => 'Ahmed', 'prenom' => 'Mohamed', 'reference' => 'INT-2026-003', 'status' => 'active', 'is_active' => true]);
            return [$emp, $con, $vis, $int];
        });

        // Matricule exact → uniquement l'employé
        $this->assertEquals([['type' => 'employee', 'id' => $emp->id, 'identifier' => '100289']], $this->search($admin, '100289'));

        // Nom « Mohamed » → LES QUATRE, distincts par (type,id)
        $byName = $this->search($admin, 'Mohamed');
        $this->assertCount(4, $byName);
        $keys = collect($byName)->map(fn ($p) => $p['type'])->sort()->values()->all();
        $this->assertEquals(['contractor', 'employee', 'intern', 'visitor'], $keys);

        // Filtre par type
        $this->assertEquals([['type' => 'visitor', 'id' => $vis->id, 'identifier' => 'VIS-2026-044']], $this->search($admin, 'Mohamed', 'visitor'));

        // Référence stagiaire / badge contractor / entreprise
        $this->assertEquals('intern', $this->search($admin, 'INT-2026-003')[0]['type']);
        $this->assertEquals('contractor', $this->search($admin, 'CONT-012')[0]['type']);
        $this->assertEquals('contractor', $this->search($admin, 'ABC Construction')[0]['type']);

        // Aucun résultat
        $this->assertSame([], $this->search($admin, 'zzz-introuvable'));
    }

    public function test_recherche_isolee_par_tenant(): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);
        $adminA  = $this->admin($tenantA);

        $this->inTenant($tenantB, fn () => Employee::factory()->create(['tenant_id' => $tenantB->id, 'nom' => 'Secret', 'prenom' => 'B', 'matricule' => '999999']));

        $this->assertSame([], $this->search($adminA, '999999'));
        $this->assertSame([], $this->search($adminA, 'Secret'));
    }
}
