<?php

namespace Tests\Feature\Api;

use App\Models\SafetyIncident;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentTest extends TestCase
{
    use RefreshDatabase;

    private function createTenantAdmin(): array
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $admin  = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'company_admin',
            'is_active' => true,
        ]);
        return [$tenant, $admin];
    }

    public function test_create_incident(): void
    {
        [$tenant, $admin] = $this->createTenantAdmin();

        $this->actingAs($admin)
            ->postJson('/api/v1/incidents', [
                'date'        => '2026-06-30',
                'time'        => '14:30',
                'location'    => 'Zone A - Quai 3',
                'type'        => 'LTI',
                'severity'    => 'low',
                'description' => 'Glissade sur le quai',
            ])
            ->assertStatus(201)
            ->assertJsonPath('reference', fn($v) => str_starts_with($v ?? '', 'INC-2026-'));

        $this->assertDatabaseHas('safety_incidents', [
            'tenant_id' => $tenant->id,
            'type'      => 'LTI',
            'severity'  => 'low',
        ]);
    }

    /**
     * Non-regression B3 : la validation HTTP et la contrainte CHECK PostgreSQL
     * declaraient deux vocabulaires differents pour `type`. `FIRE` et `FIRST_AID`
     * passaient la validation puis explosaient a l'insertion (QueryException -> 500).
     * Chaque type canonique doit desormais etre reellement insérable.
     */
    public function test_every_canonical_type_is_accepted(): void
    {
        [$tenant, $admin] = $this->createTenantAdmin();

        foreach (SafetyIncident::TYPES as $type) {
            $this->actingAs($admin)
                ->postJson('/api/v1/incidents', [
                    'date'        => '2026-06-30',
                    'location'    => 'Zone A',
                    'type'        => $type,
                    'severity'    => 'low',
                    'description' => 'Verification du vocabulaire canonique',
                ])
                ->assertStatus(201, "Le type canonique {$type} a ete refuse.")
                ->assertJsonPath('type', $type);

            $this->assertDatabaseHas('safety_incidents', [
                'tenant_id' => $tenant->id,
                'type'      => $type,
            ]);
        }
    }

    /**
     * Un type hors vocabulaire doit etre rejete proprement par la validation (422),
     * jamais atteindre la base et produire une 500.
     */
    public function test_unknown_type_is_rejected_with_422(): void
    {
        [, $admin] = $this->createTenantAdmin();

        $this->actingAs($admin)
            ->postJson('/api/v1/incidents', [
                'date'        => '2026-06-30',
                'location'    => 'Zone A',
                'type'        => 'NOT_A_TYPE',
                'severity'    => 'low',
                'description' => 'Type inconnu',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    /**
     * L'import Excel normalisait vers 'FAT','MTI','FA','PP','autre' : aucune de ces
     * valeurs n'etait acceptee par la base, donc tout import echouait. Les alias surs
     * doivent converger vers le vocabulaire canonique, et l'inconnu retomber sur 'Autre'
     * plutot que d'etre rapproche d'un type voisin (falsification des indicateurs HSE).
     */
    public function test_type_normalization_maps_aliases_and_defaults_to_autre(): void
    {
        $this->assertSame('FAC', SafetyIncident::normalizeType('FIRST_AID'));
        $this->assertSame('FAC', SafetyIncident::normalizeType('FA'));
        $this->assertSame('Fire', SafetyIncident::normalizeType('FIRE'));
        $this->assertSame('MTC', SafetyIncident::normalizeType('MTI'));
        $this->assertSame('LTI', SafetyIncident::normalizeType('LTI'));
        $this->assertSame('Autre', SafetyIncident::normalizeType('autre'));

        // Codes sans equivalent semantique : jamais rapproches d'un autre type.
        $this->assertSame('Autre', SafetyIncident::normalizeType('FAT'));
        $this->assertSame('Autre', SafetyIncident::normalizeType('PP'));
        $this->assertSame('Autre', SafetyIncident::normalizeType(''));
    }

    public function test_incident_reference_is_tenant_scoped(): void
    {
        [$tenantA, $adminA] = $this->createTenantAdmin();
        [$tenantB, $adminB] = $this->createTenantAdmin();

        $payload = [
            'date'        => '2026-06-30',
            'location'    => 'Zone B',
            'type'        => 'LTI',
            'severity'    => 'low',
            'description' => 'Test',
        ];

        $this->actingAs($adminA)
            ->postJson('/api/v1/incidents', $payload)
            ->assertStatus(201)
            ->assertJsonPath('reference', 'INC-2026-0001');

        $this->actingAs($adminB)
            ->postJson('/api/v1/incidents', $payload)
            ->assertStatus(201)
            ->assertJsonPath('reference', 'INC-2026-0001');
    }

    public function test_close_incident_requires_root_cause(): void
    {
        [$tenant, $admin] = $this->createTenantAdmin();

        $incident = SafetyIncident::factory()->create([
            'tenant_id'   => $tenant->id,
            'reported_by' => $admin->id,
            'status'      => 'open',
        ]);

        $this->actingAs($admin)
            ->postJson("/api/v1/incidents/{$incident->id}/close", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['root_cause', 'corrective_action']);
    }

    public function test_close_incident_success(): void
    {
        [$tenant, $admin] = $this->createTenantAdmin();

        $incident = SafetyIncident::factory()->create([
            'tenant_id'   => $tenant->id,
            'reported_by' => $admin->id,
            'status'      => 'open',
        ]);

        $this->actingAs($admin)
            ->postJson("/api/v1/incidents/{$incident->id}/close", [
                'root_cause'        => 'Sol glissant non signale',
                'corrective_action' => 'Pose de revetement antiderapant',
            ])
            ->assertStatus(200)
            ->assertJsonFragment(['status' => 'closed']);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/incidents')->assertStatus(401);
    }

    /**
     * Règle métier (prompt maître §11) : un agent terrain PEUT signaler un incident,
     * mais la gestion du cycle de vie (suppression / clôture) reste réservée aux
     * responsables. Remplace l'ancien test_agent_cannot_create_incident, dont la règle
     * (agent bloqué à la création) est devenue obsolète avec l'ouverture au signalement mobile.
     */
    public function test_agent_can_report_incident_but_not_delete(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $agent  = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'agent',
            'is_active' => true,
        ]);

        // Signalement autorisé pour un agent.
        $response = $this->actingAs($agent)
            ->postJson('/api/v1/incidents', [
                'date'        => '2026-06-30',
                'location'    => 'Zone A',
                'type'        => 'LTI',
                'severity'    => 'low',
                'description' => 'Test',
            ])
            ->assertStatus(201);

        $incidentId = $response->json('id');

        // Suppression interdite pour un agent (réservée aux responsables).
        $this->actingAs($agent)
            ->deleteJson("/api/v1/incidents/{$incidentId}")
            ->assertStatus(403);
    }
}
