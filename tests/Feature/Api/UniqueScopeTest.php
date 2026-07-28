<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unicité métier scopée par tenant (prompt maître §15/§1) : deux entreprises peuvent
 * réutiliser la même valeur (matricule, code, nom de département), mais un doublon
 * reste interdit à l'intérieur d'un même tenant.
 */
class UniqueScopeTest extends TestCase
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

    private function employeePayload(string $matricule): array
    {
        return [
            'matricule'     => $matricule,
            'nom'           => 'Nom',
            'prenom'        => 'Prenom',
            'poste'         => 'Technicien',
            'type_contrat'  => 'CDI',
            'date_embauche' => '2024-01-01',
        ];
    }

    public function test_same_matricule_allowed_across_different_tenants(): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);

        $this->actingAs($this->admin($tenantA))
            ->postJson('/api/v1/employees', $this->employeePayload('EMP001'))
            ->assertStatus(201);

        // Même matricule dans une AUTRE entreprise : autorisé.
        $this->actingAs($this->admin($tenantB))
            ->postJson('/api/v1/employees', $this->employeePayload('EMP001'))
            ->assertStatus(201);

        $this->assertDatabaseHas('employees', ['tenant_id' => $tenantA->id, 'matricule' => 'EMP001']);
        $this->assertDatabaseHas('employees', ['tenant_id' => $tenantB->id, 'matricule' => 'EMP001']);
    }

    public function test_duplicate_matricule_rejected_within_same_tenant(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $admin  = $this->admin($tenant);

        $this->actingAs($admin)
            ->postJson('/api/v1/employees', $this->employeePayload('EMP001'))
            ->assertStatus(201);

        // Même matricule dans le MÊME tenant : refusé (422).
        $this->actingAs($admin)
            ->postJson('/api/v1/employees', $this->employeePayload('EMP001'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['matricule']);
    }

    public function test_same_department_name_allowed_across_tenants_but_not_within(): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);

        $this->actingAs($this->admin($tenantA))
            ->postJson('/api/v1/departments', ['name' => 'Sécurité'])
            ->assertStatus(201);

        // Autre tenant : autorisé.
        $this->actingAs($this->admin($tenantB))
            ->postJson('/api/v1/departments', ['name' => 'Sécurité'])
            ->assertStatus(201);

        // Même tenant : refusé.
        $this->actingAs($this->admin($tenantA))
            ->postJson('/api/v1/departments', ['name' => 'Sécurité'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }
}
