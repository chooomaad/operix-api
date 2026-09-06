<?php

namespace Tests\Feature\Api;

use App\Models\Risk;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiskTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Tenant $t): User
    {
        return User::factory()->create(['tenant_id' => $t->id, 'role' => 'company_admin', 'is_active' => true]);
    }

    private function payload(array $o = []): array
    {
        return array_merge([
            'date_identification' => '2026-09-01',
            'location'            => 'Quai 3',
            'category'            => 'work_at_height',
            'assessment_type'     => 'hira',
            'danger'              => 'Travail en hauteur sans protection',
            'risk_description'    => 'Chute de hauteur',
            'probability'         => 4,
            'severity'            => 5,
        ], $o);
    }

    public function test_create_risk_computes_score_and_level(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);

        $this->actingAs($this->admin($t))
            ->postJson('/api/v1/risks', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('score', 20)          // 4 × 5
            ->assertJsonPath('level', 'critical')  // 17–25
            ->assertJsonPath('reference', fn ($v) => str_starts_with($v ?? '', 'RM-2026-'));

        $this->assertDatabaseHas('risks', ['tenant_id' => $t->id, 'score' => 20, 'level' => 'critical']);
    }

    public function test_level_bands_are_correct(): void
    {
        $this->assertSame('low', Risk::levelForScore(4));
        $this->assertSame('medium', Risk::levelForScore(5));
        $this->assertSame('medium', Risk::levelForScore(9));
        $this->assertSame('high', Risk::levelForScore(10));
        $this->assertSame('high', Risk::levelForScore(16));
        $this->assertSame('critical', Risk::levelForScore(17));
        $this->assertSame('critical', Risk::levelForScore(25));
    }

    public function test_residual_risk_is_computed_when_provided(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);

        $this->actingAs($this->admin($t))
            ->postJson('/api/v1/risks', $this->payload([
                'residual_probability' => 2,
                'residual_severity'    => 3,
                'controls'             => [
                    ['hierarchy' => 'engineering', 'description' => 'Garde-corps'],
                    ['hierarchy' => 'ppe', 'description' => 'Harnais'],
                ],
            ]))
            ->assertStatus(201)
            ->assertJsonPath('residual_score', 6)
            ->assertJsonPath('residual_level', 'medium')
            ->assertJsonPath('controls.0.hierarchy', 'engineering');
    }

    public function test_probability_out_of_range_is_rejected(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $this->actingAs($this->admin($t))
            ->postJson('/api/v1/risks', $this->payload(['probability' => 6]))
            ->assertStatus(422)->assertJsonValidationErrors(['probability']);
    }

    public function test_unknown_category_is_rejected(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $this->actingAs($this->admin($t))
            ->postJson('/api/v1/risks', $this->payload(['category' => 'volcano']))
            ->assertStatus(422)->assertJsonValidationErrors(['category']);
    }

    public function test_reference_is_tenant_scoped(): void
    {
        $a = Tenant::factory()->create(['status' => 'active']);
        $b = Tenant::factory()->create(['status' => 'active']);
        $this->actingAs($this->admin($a))->postJson('/api/v1/risks', $this->payload())
            ->assertStatus(201)->assertJsonPath('reference', 'RM-2026-0001');
        $this->actingAs($this->admin($b))->postJson('/api/v1/risks', $this->payload())
            ->assertStatus(201)->assertJsonPath('reference', 'RM-2026-0001');
    }

    public function test_agent_can_view_but_not_create(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $agent = User::factory()->create(['tenant_id' => $t->id, 'role' => 'agent', 'is_active' => true]);

        $this->actingAs($agent)->getJson('/api/v1/risks')->assertStatus(200);
        $this->actingAs($agent)->postJson('/api/v1/risks', $this->payload())->assertStatus(403);
    }

    public function test_dashboard_returns_matrix_and_aggregates(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = $this->admin($t);
        $this->actingAs($admin)->postJson('/api/v1/risks', $this->payload(['probability' => 4, 'severity' => 5]))->assertStatus(201);
        $this->actingAs($admin)->postJson('/api/v1/risks', $this->payload(['probability' => 1, 'severity' => 2]))->assertStatus(201);

        $this->actingAs($admin)->getJson('/api/v1/risks/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('total', 2)
            ->assertJsonPath('by_level.critical', 1)
            ->assertJsonPath('by_level.low', 1)
            ->assertJsonPath('matrix.4_5', 1)
            ->assertJsonPath('matrix.1_2', 1)
            ->assertJsonPath('critical_open', 1);
    }
}
