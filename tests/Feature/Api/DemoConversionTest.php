<?php

namespace Tests\Feature\Api;

use App\Models\DemoRequest;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoConversionTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['tenant_id' => null, 'role' => 'super_admin']);
    }

    private function demo(): DemoRequest
    {
        return DemoRequest::create([
            'company_name' => 'Port Beta',
            'contact_name' => 'Sidi Ould',
            'email'        => 'sidi@port-beta.mr',
            'status'       => 'approved',
        ]);
    }

    public function test_superadmin_converts_demo_to_trial_tenant(): void
    {
        Plan::factory()->create(['slug' => 'starter']);
        $demo = $this->demo();

        $this->actingAs($this->superAdmin())
            ->postJson("/api/v1/superadmin/demo-requests/{$demo->id}/convert", [
                'plan_slug'  => 'starter',
                'trial_days' => 14,
            ])
            ->assertStatus(201)
            ->assertJsonPath('tenant.status', 'trial');

        $this->assertDatabaseHas('demo_requests', ['id' => $demo->id, 'status' => 'converted']);
        $this->assertDatabaseHas('users', ['email' => 'sidi@port-beta.mr', 'role' => 'company_admin']);
        $this->assertDatabaseHas('subscriptions', ['status' => 'trialing']);
        $this->assertNotNull($demo->fresh()->tenant_id);
    }

    public function test_demo_conversion_is_idempotent(): void
    {
        Plan::factory()->create(['slug' => 'starter']);
        $demo = $this->demo();
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->postJson("/api/v1/superadmin/demo-requests/{$demo->id}/convert", ['plan_slug' => 'starter'])
            ->assertStatus(201);

        // 2e conversion refusée → aucun second tenant.
        $this->actingAs($admin)
            ->postJson("/api/v1/superadmin/demo-requests/{$demo->id}/convert", ['plan_slug' => 'starter'])
            ->assertStatus(422);

        $this->assertSame(1, Tenant::count());
    }

    public function test_company_admin_cannot_convert_demo(): void
    {
        Plan::factory()->create(['slug' => 'starter']);
        $demo   = $this->demo();
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $admin  = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'company_admin']);

        $this->actingAs($admin)
            ->postJson("/api/v1/superadmin/demo-requests/{$demo->id}/convert", ['plan_slug' => 'starter'])
            ->assertStatus(403);
    }
}
