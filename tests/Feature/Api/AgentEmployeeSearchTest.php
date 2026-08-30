<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Recherche employe reservee a l'AGENT — securite et projection minimale.
 *
 * Verifie que l'agent obtient exactement 3 champs (matricule, nom, statut), jamais
 * une donnee sensible, jamais un employe d'une autre entreprise, et que l'endpoint
 * est reserve au role agent, refuse aux comptes/tenants inactifs, borne et limite.
 */
class AgentEmployeeSearchTest extends TestCase
{
    use RefreshDatabase;

    private function agent(Tenant $tenant): User
    {
        $u = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'agent',
            'is_active' => true,
        ]);
        $u->assignRole('agent');
        return $u;
    }

    private function employee(Tenant $tenant, array $attrs = []): Employee
    {
        return app(TenantContext::class)->runWithoutScope(function () use ($tenant, $attrs) {
            app(TenantContext::class)->set($tenant->id);
            try {
                return Employee::factory()->create(array_merge([
                    'tenant_id' => $tenant->id,
                ], $attrs));
            } finally {
                app(TenantContext::class)->clear();
            }
        });
    }

    private function url(string $q): string
    {
        return '/api/v1/agent/employees/search?q=' . urlencode($q);
    }

    public function test_1_agent_recherche_un_employe_de_son_tenant(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $this->employee($tenant, ['matricule' => 'TCN00125', 'nom' => 'Ahmed', 'prenom' => 'Mohamed']);

        $this->actingAs($this->agent($tenant))
            ->getJson($this->url('Mohamed'))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_2_recherche_par_matricule(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $this->employee($tenant, ['matricule' => 'TCN00125', 'nom' => 'Ahmed', 'prenom' => 'Mohamed']);

        $this->actingAs($this->agent($tenant))
            ->getJson($this->url('TCN00125'))
            ->assertOk()
            ->assertJsonPath('data.0.matricule', 'TCN00125');
    }

    public function test_3_recherche_par_nom(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $this->employee($tenant, ['matricule' => 'TCN00200', 'nom' => 'Salem', 'prenom' => 'Fatima']);

        $this->actingAs($this->agent($tenant))
            ->getJson($this->url('Salem'))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Fatima Salem');
    }

    public function test_4_5_resultat_ne_contient_que_matricule_nom_statut(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $this->employee($tenant, [
            'matricule' => 'TCN00300', 'nom' => 'Ould', 'prenom' => 'Sidi',
            'email' => 'sidi@perso.mr', 'phone' => '22233344', 'adresse' => 'Nouakchott',
            'num_cni' => '1234567890', 'nni' => '9876543210',
        ]);

        $res = $this->actingAs($this->agent($tenant))->getJson($this->url('Sidi'))->assertOk();

        $res->assertJsonStructure(['data' => [['matricule', 'name', 'status']]]);
        $keys = array_keys($res->json('data.0'));
        sort($keys);
        $this->assertSame(['matricule', 'name', 'status'], $keys);

        $body = $res->getContent();
        foreach (['sidi@perso.mr', '22233344', 'Nouakchott', '1234567890', '9876543210',
                  'email', 'phone', 'adresse', 'num_cni', 'nni', 'date_naissance',
                  'salaire', 'department', 'poste', 'incidents'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $body, "Champ interdit expose: {$forbidden}");
        }
    }

    public function test_6_isolation_tenant_aucun_employe_d_un_autre_tenant(): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);

        $this->employee($tenantA, ['matricule' => 'A-001', 'nom' => 'Kane', 'prenom' => 'Omar']);
        $this->employee($tenantB, ['matricule' => 'B-001', 'nom' => 'Kane', 'prenom' => 'Omar']);

        $this->actingAs($this->agent($tenantA))
            ->getJson($this->url('Kane'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.matricule', 'A-001');
    }

    public function test_7_un_non_agent_ne_peut_pas_utiliser_l_endpoint(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $admin  = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'company_admin', 'is_active' => true]);
        $admin->assignRole('company_admin');

        $this->actingAs($admin)->getJson($this->url('Mohamed'))->assertStatus(403);
    }

    public function test_8_tenant_suspendu_refuse(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $agent  = $this->agent($tenant);
        $tenant->update(['status' => 'suspended']);

        $this->actingAs($agent)->getJson($this->url('Mohamed'))->assertStatus(403);
    }

    public function test_9_compte_desactive_refuse(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $agent  = $this->agent($tenant);
        $agent->update(['is_active' => false]);

        $this->actingAs($agent->fresh())->getJson($this->url('Mohamed'))->assertStatus(403);
    }

    public function test_10_resultats_limites_a_20(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        app(TenantContext::class)->runWithoutScope(function () use ($tenant) {
            app(TenantContext::class)->set($tenant->id);
            try {
                Employee::factory()->count(25)
                    ->sequence(fn ($s) => ['matricule' => 'LIM-' . $s->index, 'prenom' => 'Mohamed'])
                    ->create(['tenant_id' => $tenant->id]);
            } finally {
                app(TenantContext::class)->clear();
            }
        });

        $this->actingAs($this->agent($tenant))
            ->getJson($this->url('Mohamed'))
            ->assertOk()
            ->assertJsonCount(20, 'data');
    }

    public function test_11_recherche_trop_courte_est_rejetee(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        $this->actingAs($this->agent($tenant))
            ->getJson($this->url('a'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('q');
    }

    public function test_12_rate_limit_protege_l_endpoint(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $agent  = $this->agent($tenant);

        $last = null;
        for ($i = 0; $i < 32; $i++) {
            $last = $this->actingAs($agent)->getJson($this->url('Mohamed'));
        }
        $last->assertStatus(429);
    }
}
