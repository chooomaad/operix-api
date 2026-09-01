<?php

namespace Tests\Feature\Api;

use App\Models\EnvironmentReport;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Environnement : personnes impliquees sauvegardees, remplacees a la modification
 * (sans relation fantome), et remontees dans l'historique HSSE des employes.
 */
class EnvironmentInvolvedTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Tenant $t): User
    {
        return User::factory()->create(['tenant_id' => $t->id, 'role' => 'company_admin', 'is_active' => true]);
    }

    private function emp(Tenant $t): Employee
    {
        return app(TenantContext::class)->runWithoutScope(function () use ($t) {
            app(TenantContext::class)->set($t->id);
            try { return Employee::factory()->create(['tenant_id' => $t->id]); }
            finally { app(TenantContext::class)->clear(); }
        });
    }

    public function test_personnes_impliquees_sauvegardees_puis_remplacees(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = $this->admin($t);
        [$a, $b, $c] = [$this->emp($t), $this->emp($t), $this->emp($t)];

        $id = $this->actingAs($admin)->postJson('/api/v1/environment', [
            'date' => '2026-08-31', 'location' => 'Parc', 'type' => 'waste',
            'severity' => 'medium', 'description' => 'Rapport.',
            'involved_people' => [['type' => 'employee', 'id' => $a->id], ['type' => 'employee', 'id' => $b->id]],
        ])->assertStatus(201)->json('id');

        $this->assertCount(2, EnvironmentReport::withoutGlobalScopes()->find($id)->involved_people);

        // Modification : A retire, C ajoute, B conserve.
        $this->actingAs($admin)->putJson("/api/v1/environment/{$id}", ['involved_people' => [['type' => 'employee', 'id' => $b->id], ['type' => 'employee', 'id' => $c->id]]])
            ->assertOk();

        $after = EnvironmentReport::withoutGlobalScopes()->find($id)->involved_people;
        $this->assertCount(2, $after);
        $this->assertNotContains(['type' => 'employee', 'id' => $a->id], $after); // aucune relation fantome

        // Historique : A ne l'a plus, B et C l'ont.
        $this->assertCount(0, $this->actingAs($admin)->getJson("/api/v1/employees/{$a->id}/history")->json('environment'));
        $this->assertCount(1, $this->actingAs($admin)->getJson("/api/v1/employees/{$b->id}/history")->json('environment'));
        $this->assertCount(1, $this->actingAs($admin)->getJson("/api/v1/employees/{$c->id}/history")->json('environment'));
    }
}
