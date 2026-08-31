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
            'role'      => 'company_admin',
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

    public function test_last_login_is_recorded_on_login_and_returned_in_the_list(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $admin  = $this->adminFor($tenant);

        $member = User::factory()->create([
            'tenant_id'     => $tenant->id,
            'role'          => 'hsse_manager',
            'matricule'     => 'TCN-LL-001',
            'is_active'     => true,
            'password'      => \Illuminate\Support\Facades\Hash::make('7391'),
            'last_login_at' => null,
        ]);

        // Avant toute connexion : pas de derniere connexion.
        $this->assertNull($member->last_login_at);

        // Le membre se connecte -> le login enregistre last_login_at.
        $this->postJson('/api/v1/auth/login', [
            'matricule' => 'TCN-LL-001', 'pin' => '7391', 'platform' => 'web',
        ])->assertOk();

        $this->assertNotNull($member->fresh()->last_login_at);

        // La liste Users (vue admin) expose bien last_login_at, non nul pour ce membre.
        $row = collect(
            $this->actingAs($admin)->getJson('/api/v1/users')->assertOk()->json('data')
        )->firstWhere('id', $member->id);

        $this->assertNotNull($row['last_login_at'] ?? null);
    }

    public function test_admin_can_delete_a_user_of_own_tenant(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $admin  = $this->adminFor($tenant);
        $target = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'agent',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/users/{$target->id}")
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $admin  = $this->adminFor($tenant);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/users/{$admin->id}")
            ->assertStatus(422);

        // Le compte est toujours là.
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_deleting_a_user_keeps_their_reported_incidents_with_null_reporter(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $admin  = $this->adminFor($tenant);
        $agent  = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => 'agent', 'is_active' => true,
        ]);

        // Un incident signalé par l'agent (créé hors requête, tenant posé).
        $incident = app(\App\Support\TenantContext::class)->runWithoutScope(function () use ($tenant, $agent) {
            app(\App\Support\TenantContext::class)->set($tenant->id);
            try {
                return \App\Models\SafetyIncident::create([
                    'reference'   => 'INC-DEL-0001',
                    'date'        => '2026-08-30',
                    'location'    => 'Quai',
                    'type'        => 'FAC',
                    'severity'    => 'low',
                    'description' => 'Incident conserve apres suppression du compte.',
                    'status'      => 'open',
                    'reported_by' => $agent->id,
                ]);
            } finally {
                app(\App\Support\TenantContext::class)->clear();
            }
        });

        $this->actingAs($admin)->deleteJson("/api/v1/users/{$agent->id}")->assertOk();

        // L'incident survit ; seul son auteur passe à null (FK nullOnDelete).
        $this->assertDatabaseHas('safety_incidents', [
            'id'          => $incident->id,
            'reported_by' => null,
        ]);
    }
}
