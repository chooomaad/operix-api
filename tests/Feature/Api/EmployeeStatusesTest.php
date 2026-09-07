<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\PpeIssuance;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'annuaire et la recherche agent exposent deux statuts opérationnels par employé :
 * induction (faite ?) et EPI (dotation remise ?).
 */
class EmployeeStatusesTest extends TestCase
{
    use RefreshDatabase;

    private function makeEmployee(Tenant $t, bool $induction, bool $withPpe): Employee
    {
        return app(TenantContext::class)->runWithoutScope(function () use ($t, $induction, $withPpe) {
            app(TenantContext::class)->set($t->id);
            try {
                $emp = Employee::create([
                    'tenant_id' => $t->id, 'matricule' => 'EMP-STA-1', 'nom' => 'Sy', 'prenom' => 'Fatima',
                    'poste' => 'Grutier', 'type_contrat' => 'CDI', 'is_active' => true,
                    'induction_status' => $induction,
                ]);
                if ($withPpe) {
                    PpeIssuance::create([
                        'tenant_id' => $t->id, 'person_type' => 'employee', 'person_id' => $emp->id,
                        'items' => ['helmet'], 'categories' => ['head'], 'issued_at' => '2026-09-01',
                    ]);
                }
                return $emp;
            } finally {
                app(TenantContext::class)->clear();
            }
        });
    }

    public function test_directory_exposes_induction_and_ppe(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = User::factory()->create(['tenant_id' => $t->id, 'role' => 'company_admin', 'is_active' => true]);
        $this->makeEmployee($t, induction: true, withPpe: true);

        $this->actingAs($admin)->getJson('/api/v1/employees?search=Sy')
            ->assertStatus(200)
            ->assertJsonPath('data.0.induction', true)
            ->assertJsonPath('data.0.has_ppe', true);
    }

    public function test_directory_flags_missing_induction_and_ppe(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = User::factory()->create(['tenant_id' => $t->id, 'role' => 'company_admin', 'is_active' => true]);
        $this->makeEmployee($t, induction: false, withPpe: false);

        $this->actingAs($admin)->getJson('/api/v1/employees?search=Sy')
            ->assertStatus(200)
            ->assertJsonPath('data.0.induction', false)
            ->assertJsonPath('data.0.has_ppe', false);
    }

    public function test_agent_search_shows_both_statuses_for_employee(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $agent = User::factory()->create(['tenant_id' => $t->id, 'role' => 'agent', 'is_active' => true]);
        $this->makeEmployee($t, induction: true, withPpe: false);

        $this->actingAs($agent)->getJson('/api/v1/agent/people/search?q=Sy')
            ->assertStatus(200)
            ->assertJsonPath('data.0.type', 'employee')
            ->assertJsonPath('data.0.induction', true)
            ->assertJsonPath('data.0.has_ppe', false);
    }
}
