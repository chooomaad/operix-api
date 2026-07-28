<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminCommerceTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['tenant_id' => null, 'role' => 'super_admin']);
    }

    public function test_dashboard_counts_across_tenants_via_explicit_bypass(): void
    {
        $tA = Tenant::factory()->create(['status' => 'active']);
        $tB = Tenant::factory()->create(['status' => 'trial']);
        Employee::factory()->create(['tenant_id' => $tA->id]);
        Employee::factory()->create(['tenant_id' => $tB->id]);

        $response = $this->actingAs($this->superAdmin())
            ->getJson('/api/v1/superadmin/dashboard')
            ->assertOk();

        // Le comptage cross-tenant fonctionne grâce au bypass explicite (sinon 0, fail-closed).
        $response->assertJsonPath('platform.total_employees', 2);
        $response->assertJsonPath('platform.total_tenants', 2);
        $this->assertArrayHasKey('commercial', $response->json());
    }

    public function test_superadmin_can_manage_plan_prices(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($super)->postJson('/api/v1/superadmin/plans', [
            'slug' => 'pro', 'name' => 'Pro', 'price_monthly' => 9900, 'currency' => 'EUR',
        ])->assertStatus(201);

        $plan = Plan::where('slug', 'pro')->firstOrFail();

        $this->actingAs($super)->putJson("/api/v1/superadmin/plans/{$plan->id}", [
            'price_monthly' => 12900,
        ])->assertOk();

        $this->assertDatabaseHas('plans', ['id' => $plan->id, 'price_monthly' => 12900]);
    }

    public function test_superadmin_can_list_commercial_resources(): void
    {
        $super = $this->superAdmin();
        Order::factory()->create();

        foreach (['orders', 'payments', 'subscriptions', 'plans'] as $resource) {
            $this->actingAs($super)->getJson("/api/v1/superadmin/{$resource}")->assertOk();
        }
    }

    public function test_company_admin_is_forbidden_from_commercial_superadmin(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $admin  = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'company_admin']);

        foreach (['orders', 'payments', 'subscriptions', 'plans', 'dashboard'] as $resource) {
            $this->actingAs($admin)->getJson("/api/v1/superadmin/{$resource}")->assertStatus(403);
        }
    }
}
