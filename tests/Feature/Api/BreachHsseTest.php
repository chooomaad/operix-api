<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le module Breaches est aligne sur le modele HSSE : plusieurs personnes
 * impliquees (employees) + gravite HSSE (low/medium/high/critical), et l'evenement
 * remonte dans l'historique HSSE des employes concernes.
 */
class BreachHsseTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Tenant $tenant): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => 'company_admin', 'is_active' => true,
        ]);
    }

    private function employee(Tenant $tenant, array $attrs = []): Employee
    {
        return app(TenantContext::class)->runWithoutScope(function () use ($tenant, $attrs) {
            app(TenantContext::class)->set($tenant->id);
            try { return Employee::factory()->create(array_merge(['tenant_id' => $tenant->id], $attrs)); }
            finally { app(TenantContext::class)->clear(); }
        });
    }

    public function test_creation_breach_avec_plusieurs_personnes_et_gravite_hsse(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $admin  = $this->admin($tenant);
        $a = $this->employee($tenant);
        $b = $this->employee($tenant);

        $res = $this->actingAs($admin)->postJson('/api/v1/breaches', [
            'date' => '2026-08-31', 'location' => 'Quai', 'type' => 'epi',
            'severity' => 'high', 'description' => 'Infraction EPI.',
            'corrective_action' => 'Rappel consignes.',
            'involved_people' => [['type' => 'employee', 'id' => $a->id], ['type' => 'employee', 'id' => $b->id]],
        ])->assertStatus(201);

        $res->assertJsonPath('severity', 'high');
        $this->assertCount(2, $res->json('involved_people'));
    }

    public function test_gravite_disciplinaire_est_refusee(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $admin  = $this->admin($tenant);

        // L'ancienne gravite disciplinaire n'est plus valide.
        $this->actingAs($admin)->postJson('/api/v1/breaches', [
            'date' => '2026-08-31', 'location' => 'Quai', 'type' => 'epi',
            'severity' => 'avertissement', 'description' => 'x',
        ])->assertStatus(422)->assertJsonValidationErrors('severity');
    }

    public function test_le_breach_remonte_dans_l_historique_de_l_employe(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $admin  = $this->admin($tenant);
        $emp    = $this->employee($tenant);

        $this->actingAs($admin)->postJson('/api/v1/breaches', [
            'date' => '2026-08-31', 'location' => 'Quai', 'type' => 'epi',
            'severity' => 'medium', 'description' => 'Infraction.',
            'involved_people' => [['type' => 'employee', 'id' => $emp->id]],
        ])->assertStatus(201);

        $history = $this->actingAs($admin)->getJson("/api/v1/employees/{$emp->id}/history")->assertOk();
        $this->assertGreaterThan(0, count($history->json('breaches') ?? []));
    }
}
