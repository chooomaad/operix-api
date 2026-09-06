<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiskActionTest extends TestCase
{
    use RefreshDatabase;

    private function setup2(): array
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = User::factory()->create(['tenant_id' => $t->id, 'role' => 'company_admin', 'is_active' => true]);
        $riskId = $this->actingAs($admin)->postJson('/api/v1/risks', [
            'date_identification' => '2026-09-01',
            'location'            => 'Quai 3',
            'category'            => 'handling',
            'danger'              => 'Manutention lourde',
            'risk_description'    => 'Écrasement',
            'probability'         => 3,
            'severity'            => 4,
        ])->assertStatus(201)->json('id');

        return [$t, $admin, $riskId];
    }

    public function test_overdue_action_is_flagged(): void
    {
        [, $admin, $riskId] = $this->setup2();
        $base = "/api/v1/risks/{$riskId}/actions";

        // Échéance passée + non terminée → en retard.
        $this->actingAs($admin)->postJson($base, [
            'description' => 'Poser une signalisation',
            'due_date'    => now()->subDays(5)->toDateString(),
        ])->assertStatus(201)->assertJsonPath('is_overdue', true);

        // Échéance future → pas en retard.
        $this->actingAs($admin)->postJson($base, [
            'description' => 'Former les opérateurs',
            'due_date'    => now()->addDays(10)->toDateString(),
        ])->assertStatus(201)->assertJsonPath('is_overdue', false);

        $this->actingAs($admin)->getJson($base)->assertOk()->assertJsonCount(2);
    }

    public function test_done_action_is_never_overdue(): void
    {
        [, $admin, $riskId] = $this->setup2();
        $this->actingAs($admin)->postJson("/api/v1/risks/{$riskId}/actions", [
            'description' => 'Corrigée',
            'due_date'    => now()->subDays(5)->toDateString(),
            'status'      => 'done',
        ])->assertStatus(201)
            ->assertJsonPath('is_overdue', false)
            ->assertJsonPath('closed_at', now()->toDateString());
    }

    public function test_hse_validation(): void
    {
        [, $admin, $riskId] = $this->setup2();
        $id = $this->actingAs($admin)->postJson("/api/v1/risks/{$riskId}/actions", [
            'description' => 'À valider',
        ])->assertStatus(201)->json('id');

        $this->actingAs($admin)->postJson("/api/v1/risks/{$riskId}/actions/{$id}/validate")
            ->assertStatus(200)
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('validator.name', $admin->name);
    }

    public function test_supervisor_cannot_validate(): void
    {
        [$t, $admin, $riskId] = $this->setup2();
        $sv = User::factory()->create(['tenant_id' => $t->id, 'role' => 'supervisor', 'is_active' => true]);
        $id = $this->actingAs($admin)->postJson("/api/v1/risks/{$riskId}/actions", ['description' => 'X'])->json('id');

        // Le superviseur peut créer une action (risks.update) mais pas valider (risks.validate).
        $this->actingAs($sv)->postJson("/api/v1/risks/{$riskId}/actions/{$id}/validate")->assertStatus(403);
    }
}
