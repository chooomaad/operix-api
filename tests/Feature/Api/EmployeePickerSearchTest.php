<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recherche employé utilisée par l'Employee Picker des 4 modules HSSE
 * (endpoint /employees?search=…&light=1). Serveur, paginée, insensible à la
 * casse, sur matricule ET nom/prénom, et strictement isolée par tenant.
 */
class EmployeePickerSearchTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Tenant $t): User
    {
        return User::factory()->create(['tenant_id' => $t->id, 'role' => 'company_admin', 'is_active' => true]);
    }

    private function emp(Tenant $t, array $attrs): Employee
    {
        return app(TenantContext::class)->runWithoutScope(function () use ($t, $attrs) {
            app(TenantContext::class)->set($t->id);
            try { return Employee::factory()->create(array_merge(['tenant_id' => $t->id, 'is_active' => true], $attrs)); }
            finally { app(TenantContext::class)->clear(); }
        });
    }

    /** @return array<int,int> ids retournés par la recherche */
    private function searchIds(User $admin, string $q): array
    {
        return collect($this->actingAs($admin)
            ->getJson('/api/v1/employees?light=1&per_page=10&search=' . urlencode($q))
            ->assertOk()->json('data'))
            ->pluck('id')->all();
    }

    public function test_recherche_matricule_nom_prenom_et_casse(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = $this->admin($t);

        $mohamed = $this->emp($t, ['matricule' => '100315', 'prenom' => 'Mohamed', 'nom' => 'Ould Ahmed']);
        $ahmed   = $this->emp($t, ['matricule' => '100316', 'prenom' => 'Ahmed',   'nom' => 'Ould Salem']);
        $other   = $this->emp($t, ['matricule' => '200999', 'prenom' => 'Fatima',  'nom' => 'Mint Baba']);

        // Matricule exact
        $this->assertEquals([$mohamed->id], $this->searchIds($admin, '100315'));

        // Matricule partiel → les deux 1003xx, pas le 2009xx
        $partial = $this->searchIds($admin, '1003');
        $this->assertContains($mohamed->id, $partial);
        $this->assertContains($ahmed->id, $partial);
        $this->assertNotContains($other->id, $partial);

        // Prénom
        $this->assertContains($mohamed->id, $this->searchIds($admin, 'Mohamed'));

        // Nom (partagé) → les deux "Ould"
        $ould = $this->searchIds($admin, 'Ould');
        $this->assertContains($mohamed->id, $ould);
        $this->assertContains($ahmed->id, $ould);

        // Insensible à la casse
        $this->assertContains($mohamed->id, $this->searchIds($admin, 'MOHAMED'));
        $this->assertContains($mohamed->id, $this->searchIds($admin, 'mohamed'));

        // Aucun résultat
        $this->assertSame([], $this->searchIds($admin, 'zzzzzz-introuvable'));
    }

    public function test_recherche_est_isolee_par_tenant(): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);
        $adminA  = $this->admin($tenantA);

        $this->emp($tenantB, ['matricule' => '777777', 'prenom' => 'Secret', 'nom' => 'TenantB']);

        // L'admin du tenant A ne doit jamais voir l'employé du tenant B.
        $this->assertSame([], $this->searchIds($adminA, '777777'));
        $this->assertSame([], $this->searchIds($adminA, 'Secret'));
    }
}
