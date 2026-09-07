<?php

namespace Tests\Feature\Api;

use App\Models\PropertyDamage;
use App\Models\Risk;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vérifie que les blades des rapports affichent RÉELLEMENT les lignes de données
 * (référence, description) quand il y en a — filet contre un rapport vide à tort.
 */
class ReportContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_damage_and_risk_reports_render_their_rows(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = User::factory()->create(['tenant_id' => $t->id, 'role' => 'company_admin', 'is_active' => true]);

        $pdId = $this->actingAs($admin)->postJson('/api/v1/property-damage', [
            'date' => '2026-09-03', 'location' => 'Quai 3', 'type' => 'container',
            'severity' => 'medium', 'description' => 'Éraflure conteneur MNBU', 'estimated_cost' => 1200,
        ])->assertStatus(201)->json('reference');

        $riskRef = $this->actingAs($admin)->postJson('/api/v1/risks', [
            'date_identification' => '2026-09-03', 'location' => 'Q3', 'category' => 'ship',
            'danger' => 'Barre de saisissage', 'risk_description' => 'Chute sur employé',
            'probability' => 3, 'severity' => 2,
        ])->assertStatus(201)->json('reference');

        // Rendu réel des blades, dans le contexte tenant.
        app(TenantContext::class)->set($t->id);
        try {
            $pd = PropertyDamage::query()->get();
            $htmlPd = view('pdf.property_damage', [
                'records' => $pd, 'brandColor' => '#0f2847', 'orgName' => 'TCN', 'orgShort' => 'TCN', 'orgLogo' => null, 'title' => 'x',
                'stats' => ['total' => $pd->count(), 'open' => 1, 'closed' => 0, 'cost' => 1200],
            ])->render();
            $this->assertStringContainsString($pdId, $htmlPd);
            $this->assertStringContainsString('Éraflure conteneur MNBU', $htmlPd);

            $risks = Risk::query()->get();
            $htmlRisk = view('pdf.risks', [
                'records' => $risks, 'brandColor' => '#0f2847', 'orgName' => 'TCN', 'orgShort' => 'TCN', 'orgLogo' => null, 'title' => 'x',
                'catLabels' => ['ship' => 'Risques navires'],
                'stats' => ['total' => $risks->count(), 'critical' => 0, 'open' => 1, 'closed' => 0],
            ])->render();
            $this->assertStringContainsString($riskRef, $htmlRisk);
            $this->assertStringContainsString('Chute sur employé', $htmlRisk);
        } finally {
            app(TenantContext::class)->clear();
        }
    }
}
