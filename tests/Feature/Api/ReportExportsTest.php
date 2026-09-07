<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exports PDF « Dommages matériels » et « Registre des risques » : réservés à
 * reports.generate, rendus en application/pdf.
 */
class ReportExportsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Tenant $t): User
    {
        return User::factory()->create(['tenant_id' => $t->id, 'role' => 'company_admin', 'is_active' => true]);
    }

    public function test_property_damage_pdf(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = $this->admin($t);
        $this->actingAs($admin)->postJson('/api/v1/property-damage', [
            'date' => '2026-09-01', 'location' => 'Quai 3', 'type' => 'vehicle',
            'severity' => 'high', 'description' => 'Collision', 'estimated_cost' => 15000,
        ])->assertStatus(201);

        $res = $this->actingAs($admin)->get('/api/v1/reports/property-damage');
        $res->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
    }

    public function test_risks_pdf(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = $this->admin($t);
        $this->actingAs($admin)->postJson('/api/v1/risks', [
            'date_identification' => '2026-09-01', 'location' => 'Quai 3',
            'category' => 'work_at_height', 'danger' => 'Chute', 'risk_description' => 'Chute de hauteur',
            'probability' => 4, 'severity' => 5,
        ])->assertStatus(201);

        $res = $this->actingAs($admin)->get('/api/v1/reports/risks');
        $res->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
    }

    public function test_agent_cannot_export(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $agent = User::factory()->create(['tenant_id' => $t->id, 'role' => 'agent', 'is_active' => true]);
        // L'agent n'a pas reports.generate.
        $this->actingAs($agent)->get('/api/v1/reports/property-damage')->assertStatus(403);
        $this->actingAs($agent)->get('/api/v1/reports/risks')->assertStatus(403);
    }
}
