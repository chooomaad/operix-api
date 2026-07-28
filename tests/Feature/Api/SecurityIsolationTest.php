<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Compléments de sécurité multi-tenant (prompt maître §5/§6) :
 * modification cross-tenant, spoofing du tenant_id à la création, blocage d'un
 * tenant suspendu sur les routes métier.
 */
class SecurityIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Tenant $tenant): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'company_admin',
            'is_active' => true,
        ]);
    }

    public function test_cannot_update_other_tenant_employee(): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);

        $adminA = $this->admin($tenantA);
        $empB   = Employee::factory()->create(['tenant_id' => $tenantB->id, 'nom' => 'Original']);

        $this->actingAs($adminA)
            ->putJson("/api/v1/employees/{$empB->id}", ['nom' => 'Intrusion'])
            ->assertStatus(404);

        $this->assertDatabaseHas('employees', ['id' => $empB->id, 'nom' => 'Original']);
    }

    public function test_employee_create_ignores_client_supplied_tenant_id(): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);

        $adminA = $this->admin($tenantA);

        $response = $this->actingAs($adminA)
            ->postJson('/api/v1/employees', [
                'matricule'     => 'EMP-9001',
                'nom'           => 'Terrain',
                'prenom'        => 'Agent',
                'poste'         => 'Technicien',
                'type_contrat'  => 'CDI',
                'date_embauche' => '2024-01-01',
                'tenant_id'     => $tenantB->id, // tentative de spoofing
            ])
            ->assertStatus(201);

        $employeeId = $response->json('id');

        // Le tenant est défini côté serveur (adminA), jamais depuis le payload.
        $this->assertDatabaseHas('employees', ['id' => $employeeId, 'tenant_id' => $tenantA->id]);
        $this->assertDatabaseMissing('employees', ['id' => $employeeId, 'tenant_id' => $tenantB->id]);
    }

    public function test_suspended_tenant_is_blocked_on_business_routes(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'suspended']);
        $admin  = $this->admin($tenant);

        $this->actingAs($admin)
            ->getJson('/api/v1/employees')
            ->assertStatus(403);
    }
}
