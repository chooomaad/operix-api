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

    public function test_agent_is_denied_the_full_module_and_uses_the_minimal_search(): void
    {
        // Politique (phase Agent) : l'agent n'a PLUS acces au module Employees. Il
        // dispose d'un endpoint dedie ne renvoyant que matricule / nom / statut.
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $this->seedEmployee($tenant);
        $agent = $this->userWithRole('agent', $tenant);

        // Le module complet lui est refuse.
        $this->actingAs($agent)->getJson('/api/v1/employees')->assertStatus(403);

        // Sa recherche dediee fonctionne, et n'expose aucune donnee personnelle.
        $row = $this->actingAs($agent)
            ->getJson('/api/v1/agent/employees/search?q=Ould')
            ->assertStatus(200)
            ->json('data.0');

        $this->assertSame(['matricule', 'name', 'status'], array_keys($row));
        $this->assertSame('TCN-PII-001', $row['matricule']);
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
     * La recherche GLOBALE (qui restituerait nni/phone/email en contournant
     * EmployeeResource) est desormais interdite a l'agent : il ne peut donc rien
     * en fuiter. La non-fuite est garantie a la racine, par l'absence d'acces.
     */
    public function test_global_search_is_denied_to_an_agent(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $this->seedEmployee($tenant);
        $agent = $this->userWithRole('agent', $tenant);

        $this->actingAs($agent)
            ->getJson('/api/v1/search?q=Ould')
            ->assertStatus(403);
    }
}
