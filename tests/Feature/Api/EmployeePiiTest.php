<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'annuaire du personnel est lisible par tous les roles, mais les donnees
 * personnelles ne doivent l'etre que par les detenteurs de `employees.pii.view`
 * (docs/MOBILE_API_READINESS.md §B8).
 *
 * Le point sensible est la COHERENCE entre les deux chemins de lecture : filtrer
 * EmployeeResource sans filtrer /search laisserait une porte ouverte.
 */
class EmployeePiiTest extends TestCase
{
    use RefreshDatabase;

    private const PII = [
        'num_cni',
        'adresse',
        'date_naissance',
        'lieu_naissance',
        'nationalite',
        'contact_urgence_nom',
        'contact_urgence_tel',
        'email',
        'phone',
    ];

    private function seedEmployee(Tenant $tenant): Employee
    {
        return Employee::factory()->create([
            'tenant_id'           => $tenant->id,
            'nom'                 => 'Ould Ahmed',
            'prenom'              => 'Mariem',
            'matricule'           => 'TCN-PII-001',
            'num_cni'             => '1234567890',
            'adresse'             => 'Nouakchott, Tevragh Zeina',
            'contact_urgence_nom' => 'Contact urgence',
            'contact_urgence_tel' => '+222 00 00 00 00',
        ]);
    }

    private function userWithRole(string $role, Tenant $tenant): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => $role,
            'is_active' => true,
        ]);
    }

    public function test_agent_sees_the_directory_without_personal_data(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $this->seedEmployee($tenant);
        $agent = $this->userWithRole('agent', $tenant);

        $row = $this->actingAs($agent)
            ->getJson('/api/v1/employees')
            ->assertStatus(200)
            ->json('data.0');

        // Les champs professionnels restent necessaires au travail de terrain.
        $this->assertSame('TCN-PII-001', $row['matricule']);
        $this->assertSame('Ould Ahmed', $row['nom']);

        foreach (self::PII as $field) {
            $this->assertArrayNotHasKey($field, $row, "Le champ personnel {$field} ne doit pas etre expose a un agent.");
        }
    }

    public function test_hsse_manager_sees_personal_data(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $this->seedEmployee($tenant);
        $manager = $this->userWithRole('hsse_manager', $tenant);

        $row = $this->actingAs($manager)
            ->getJson('/api/v1/employees')
            ->assertStatus(200)
            ->json('data.0');

        $this->assertSame('1234567890', $row['num_cni']);
        $this->assertSame('Nouakchott, Tevragh Zeina', $row['adresse']);
    }

    public function test_supervisor_does_not_see_personal_data_on_detail(): void
    {
        $tenant   = Tenant::factory()->create(['status' => 'active']);
        $employee = $this->seedEmployee($tenant);
        $supervisor = $this->userWithRole('supervisor', $tenant);

        $row = $this->actingAs($supervisor)
            ->getJson("/api/v1/employees/{$employee->id}")
            ->assertStatus(200)
            ->json();

        $this->assertArrayNotHasKey('num_cni', $row);
        $this->assertArrayNotHasKey('adresse', $row);
    }

    /**
     * Sans ce verrou, la recherche globale restituerait les memes donnees en
     * contournant EmployeeResource.
     */
    public function test_global_search_does_not_leak_personal_data_to_an_agent(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $this->seedEmployee($tenant);
        $agent = $this->userWithRole('agent', $tenant);

        $results = $this->actingAs($agent)
            ->getJson('/api/v1/search?q=Ould')
            ->assertStatus(200)
            ->json('employees');

        $this->assertNotEmpty($results, 'La recherche doit rester fonctionnelle pour un agent.');

        foreach (['nni', 'phone', 'email'] as $field) {
            $this->assertArrayNotHasKey($field, $results[0], "La recherche expose {$field} a un agent.");
        }
    }
}
