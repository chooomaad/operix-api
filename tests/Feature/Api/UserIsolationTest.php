<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserIsolationTest extends TestCase
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

    public function test_admin_only_lists_users_from_own_tenant(): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);
        $adminA  = $this->adminFor($tenantA);
        $userA   = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'agent']);
        $userB   = User::factory()->create(['tenant_id' => $tenantB->id, 'role' => 'agent']);
        $platform = User::factory()->create(['tenant_id' => null, 'role' => 'super_admin']);

        $response = $this->actingAs($adminA)
            ->getJson('/api/v1/users')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($adminA->id));
        $this->assertTrue($ids->contains($userA->id));
        $this->assertFalse($ids->contains($userB->id));
        $this->assertFalse($ids->contains($platform->id));
    }

    public function test_admin_cannot_read_update_or_delete_other_tenant_user(): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);
        $adminA  = $this->adminFor($tenantA);
        $userB   = User::factory()->create(['tenant_id' => $tenantB->id, 'role' => 'agent']);

        $this->actingAs($adminA)
            ->getJson("/api/v1/users/{$userB->id}")
            ->assertNotFound();

        $this->actingAs($adminA)
            ->putJson("/api/v1/users/{$userB->id}", ['name' => 'Intrusion'])
            ->assertNotFound();

        $this->actingAs($adminA)
            ->deleteJson("/api/v1/users/{$userB->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('users', [
            'id'   => $userB->id,
            'name' => $userB->name,
        ]);
    }

    public function test_new_user_tenant_is_server_defined(): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);
        $adminA  = $this->adminFor($tenantA);

        $response = $this->actingAs($adminA)
            ->postJson('/api/v1/users', [
                'name'      => 'New User',
                'email'     => 'new-user@example.com',
                'role'      => 'agent',
                'password'  => 'secret',
                'tenant_id' => $tenantB->id,
            ])
            ->assertCreated();

        $userId = $response->json('id');

        $this->assertDatabaseHas('users', [
            'id'        => $userId,
            'tenant_id' => $tenantA->id,
        ]);
        $this->assertDatabaseMissing('users', [
            'id'        => $userId,
            'tenant_id' => $tenantB->id,
        ]);
    }
}
