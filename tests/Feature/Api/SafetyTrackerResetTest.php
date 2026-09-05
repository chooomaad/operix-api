<?php

namespace Tests\Feature\Api;

use App\Models\SafetyIncident;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Compteur « jours sans accident » : remise à zéro manuelle (date de référence)
 * et remise à zéro automatique au jour de déclaration d'un LTI.
 */
class SafetyTrackerResetTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Tenant $t): User
    {
        return User::factory()->create(['tenant_id' => $t->id, 'role' => 'company_admin', 'is_active' => true]);
    }

    public function test_manual_reset_sets_counter_to_zero(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = $this->admin($t);

        // Sans incident, le compteur part du début d'année → strictement positif
        // (sauf si on est le 1er janvier ; on ne teste que la remise à zéro).
        $this->actingAs($admin)->postJson('/api/v1/safety-tracker/reset', [])
            ->assertStatus(200)
            ->assertJsonPath('days_without_accident', 0);

        // La date de référence est bien mémorisée dans les paramètres du tenant.
        $t->refresh();
        $this->assertSame(now()->toDateString(), $t->settings['safety_tracker_start_date'] ?? null);
    }

    public function test_reset_rejects_future_date(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $this->actingAs($this->admin($t))
            ->postJson('/api/v1/safety-tracker/reset', ['date' => now()->addDays(3)->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors(['date']);
    }

    public function test_lti_today_resets_counter_automatically(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = $this->admin($t);

        // On fixe d'abord une référence ancienne (le compteur monterait).
        $this->actingAs($admin)->postJson('/api/v1/safety-tracker/reset', [
            'date' => now()->subDays(40)->toDateString(),
        ])->assertStatus(200)->assertJsonPath('days_without_accident', 40);

        // Un LTI déclaré aujourd'hui ramène le compteur à 0 dès le jour de déclaration.
        SafetyIncident::factory()->create([
            'tenant_id'   => $t->id,
            'reported_by' => $admin->id,
            'type'        => 'LTI',
            'date'        => now()->toDateString(),
        ]);

        $this->actingAs($admin)->getJson('/api/v1/safety-tracker')
            ->assertStatus(200)
            ->assertJsonPath('days_without_accident', 0);
    }

    public function test_reset_forbidden_for_supervisor(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $sv = User::factory()->create(['tenant_id' => $t->id, 'role' => 'supervisor', 'is_active' => true]);
        $this->actingAs($sv)->postJson('/api/v1/safety-tracker/reset', [])->assertStatus(403);
    }
}
