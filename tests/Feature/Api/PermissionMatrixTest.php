<?php

namespace Tests\Feature\Api;

use App\Models\SafetyNearMiss;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verrouille l'autorisation applicative depuis la bascule des routes vers les
 * permissions Spatie (docs/MOBILE_API_READINESS.md §B5) et l'ouverture du
 * signalement terrain (§B2).
 *
 * Ces tests portent sur la FRONTIERE HTTP, pas sur la matrice en memoire : c'est le
 * middleware reellement pose sur la route qui doit refuser, pas une liste PHP qui
 * pourrait ne plus correspondre aux routes.
 */
class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role, ?Tenant $tenant = null): User
    {
        $tenant ??= Tenant::factory()->create(['status' => 'active']);

        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => $role,
            'is_active' => true,
        ]);
    }

    private function nearMissPayload(): array
    {
        return [
            'date'        => '2026-08-26',
            'location'    => 'Quai 3',
            'severity'    => 'medium',
            'description' => 'Charge suspendue passee au-dessus dune zone de circulation',
        ];
    }

    private function environmentPayload(): array
    {
        return [
            'date'        => '2026-08-26',
            'location'    => 'Zone de stockage',
            'type'        => 'spill',
            'severity'    => 'low',
            'description' => 'Fuite dhuile hydraulique sous un chariot',
        ];
    }

    // ── §B2 : le terrain peut signaler ────────────────────────────────────────

    public function test_agent_can_report_a_near_miss(): void
    {
        $agent = $this->userWithRole('agent');

        $this->actingAs($agent)
            ->postJson('/api/v1/near-miss', $this->nearMissPayload())
            ->assertStatus(201);

        $this->assertDatabaseHas('safety_near_miss', [
            'tenant_id'   => $agent->tenant_id,
            'reported_by' => $agent->id,
        ]);
    }

    public function test_supervisor_can_report_a_near_miss(): void
    {
        $supervisor = $this->userWithRole('supervisor');

        $this->actingAs($supervisor)
            ->postJson('/api/v1/near-miss', $this->nearMissPayload())
            ->assertStatus(201);
    }

    public function test_agent_can_report_an_environment_observation(): void
    {
        $agent = $this->userWithRole('agent');

        $this->actingAs($agent)
            ->postJson('/api/v1/environment', $this->environmentPayload())
            ->assertStatus(201);
    }

    // ── §B2 : mais pas gerer le cycle de vie ──────────────────────────────────

    public function test_agent_cannot_close_or_delete_a_near_miss(): void
    {
        $agent = $this->userWithRole('agent');

        // Cree via l'API : tenant_id n'est volontairement pas `fillable`, il est
        // auto-affecte depuis le contexte serveur (trait BelongsToTenant).
        $id = $this->actingAs($agent)
            ->postJson('/api/v1/near-miss', $this->nearMissPayload())
            ->assertStatus(201)
            ->json('id');

        $this->actingAs($agent)
            ->postJson("/api/v1/near-miss/{$id}/close", ['corrective_action' => 'x'])
            ->assertStatus(403);

        $this->actingAs($agent)
            ->putJson("/api/v1/near-miss/{$id}", ['description' => 'modifie'])
            ->assertStatus(403);

        $this->actingAs($agent)
            ->deleteJson("/api/v1/near-miss/{$id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('safety_near_miss', [
            'id'     => $id,
            'status' => 'open',
        ]);
    }

    public function test_supervisor_cannot_close_an_incident_but_can_update_it(): void
    {
        $supervisor = $this->userWithRole('supervisor');

        $incident = $this->actingAs($supervisor)
            ->postJson('/api/v1/incidents', [
                'date'        => '2026-08-26',
                'location'    => 'Quai 1',
                'type'        => 'LTI',
                'severity'    => 'low',
                'description' => 'Glissade',
            ])->assertStatus(201)->json('id');

        $this->actingAs($supervisor)
            ->putJson("/api/v1/incidents/{$incident}", ['severity' => 'medium'])
            ->assertStatus(200);

        $this->actingAs($supervisor)
            ->postJson("/api/v1/incidents/{$incident}/close", [
                'root_cause'        => 'x',
                'corrective_action' => 'y',
            ])
            ->assertStatus(403);
    }

    // ── Modules d'administration fermes au terrain ────────────────────────────

    public function test_agent_is_denied_on_administration_endpoints(): void
    {
        $agent = $this->userWithRole('agent');

        foreach (['/api/v1/users', '/api/v1/settings', '/api/v1/audit', '/api/v1/permits'] as $uri) {
            $this->actingAs($agent)
                ->getJson($uri)
                ->assertStatus(403, "L'agent ne devrait pas acceder a {$uri}.");
        }
    }

    public function test_denied_response_does_not_leak_the_expected_permission(): void
    {
        $agent = $this->userWithRole('agent');

        $this->actingAs($agent)
            ->getJson('/api/v1/users')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Accès refusé. Permission insuffisante.');
    }

    // ── L'ouverture au terrain ne perce pas l'isolation tenant ────────────────

    public function test_field_reporting_stays_isolated_between_tenants(): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);

        $agentA = $this->userWithRole('agent', $tenantA);
        $agentB = $this->userWithRole('agent', $tenantB);

        $idA = $this->actingAs($agentA)
            ->postJson('/api/v1/near-miss', $this->nearMissPayload())
            ->assertStatus(201)
            ->json('id');

        // L'agent de B ne voit pas le presqu'accident de A, et ne peut pas l'ouvrir.
        $this->actingAs($agentB)->getJson('/api/v1/near-miss')->assertStatus(200)
            ->assertJsonCount(0, 'data');

        $this->actingAs($agentB)->getJson("/api/v1/near-miss/{$idA}")->assertStatus(404);
    }

    // ── Contrat expose aux clients ────────────────────────────────────────────

    public function test_me_exposes_tenant_identity_and_abilities(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active', 'name' => 'Demo Company']);
        $agent  = $this->userWithRole('agent', $tenant);

        $response = $this->actingAs($agent)->getJson('/api/v1/auth/me')->assertStatus(200);

        $response->assertJsonPath('tenant.id', $tenant->id);
        $response->assertJsonPath('tenant.name', 'Demo Company');

        $abilities = $response->json('abilities');

        $this->assertContains('near_miss.create', $abilities);
        $this->assertContains('incidents.create', $abilities);
        $this->assertNotContains('near_miss.close', $abilities);
        $this->assertNotContains('users.manage', $abilities);
    }

    /**
     * Les permissions annoncees au client doivent correspondre exactement a celles
     * que la matrice attribue au role : un client qui affiche un bouton dont l'appel
     * sera refuse est un bug d'interface, pas de securite, mais il est evitable.
     */
    public function test_announced_abilities_match_the_matrix_for_every_role(): void
    {
        foreach (Permissions::APPLICATION_ROLES as $role) {
            $user = $this->userWithRole($role);

            $announced = $this->actingAs($user)
                ->getJson('/api/v1/auth/me')
                ->assertStatus(200)
                ->json('abilities');

            $expected = Permissions::forRole($role);
            sort($expected);

            $this->assertSame($expected, $announced, "Permissions incoherentes pour le role {$role}.");
        }
    }
}
