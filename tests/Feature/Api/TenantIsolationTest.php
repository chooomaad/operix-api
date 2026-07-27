<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\SafetyIncident;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function adminFor(Tenant $tenant): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_employee_list_only_shows_own_tenant(): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);

        $adminA = $this->adminFor($tenantA);
        $adminB = $this->adminFor($tenantB);

        $empA = Employee::factory()->create(['tenant_id' => $tenantA->id]);
        $empB = Employee::factory()->create(['tenant_id' => $tenantB->id]);

        $response = $this->actingAs($adminA)
            ->getJson('/api/v1/employees')
            ->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($empA->id));
        $this->assertFalse($ids->contains($empB->id));
    }

    public function test_cannot_read_other_tenant_employee_by_id(): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);

        $adminA = $this->adminFor($tenantA);
        $empB   = Employee::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAs($adminA)
            ->getJson("/api/v1/employees/{$empB->id}")
            ->assertStatus(404);
    }

    public function test_incident_list_only_shows_own_tenant(): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);

        $adminA = $this->adminFor($tenantA);

        $incA = SafetyIncident::factory()->create([
            'tenant_id'   => $tenantA->id,
            'reported_by' => $adminA->id,
        ]);
        $incB = SafetyIncident::factory()->create([
            'tenant_id'   => $tenantB->id,
            'reported_by' => $this->adminFor($tenantB)->id,
        ]);

        $response = $this->actingAs($adminA)
            ->getJson('/api/v1/incidents')
            ->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($incA->id));
        $this->assertFalse($ids->contains($incB->id));
    }

    public function test_cannot_delete_other_tenant_employee(): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);

        $adminA = $this->adminFor($tenantA);
        $empB   = Employee::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAs($adminA)
            ->deleteJson("/api/v1/employees/{$empB->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('employees', ['id' => $empB->id, 'deleted_at' => null]);
    }

    public function test_superadmin_route_blocked_for_regular_admin(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $admin  = $this->adminFor($tenant);

        $this->actingAs($admin)
            ->getJson('/api/v1/superadmin/dashboard')
            ->assertStatus(403);
    }
}
