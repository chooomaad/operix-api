<?php

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Filtres du journal d'audit : verbe (suffixe d'action) et recherche libre.
 */
class AuditFilterTest extends TestCase
{
    use RefreshDatabase;

    private function seedLog(Tenant $tenant, User $user, string $action, string $modelType = 'App\Models\SafetyIncident'): void
    {
        app(TenantContext::class)->runWithoutScope(function () use ($tenant, $user, $action, $modelType) {
            app(TenantContext::class)->set($tenant->id);
            try {
                ActivityLog::create([
                    'tenant_id' => $tenant->id, 'user_id' => $user->id, 'action' => $action,
                    'model_type' => $modelType, 'model_id' => 1,
                ]);
            } finally { app(TenantContext::class)->clear(); }
        });
    }

    public function test_filtre_par_verbe_created(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $admin  = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'company_admin', 'is_active' => true]);

        $this->seedLog($tenant, $admin, 'incident_created');
        $this->seedLog($tenant, $admin, 'incident_updated');
        $this->seedLog($tenant, $admin, 'employee_created');

        $data = $this->actingAs($admin)->getJson('/api/v1/audit?verb=created')->assertOk()->json('data');

        $actions = collect($data)->pluck('action')->all();
        $this->assertContains('incident_created', $actions);
        $this->assertContains('employee_created', $actions);
        $this->assertNotContains('incident_updated', $actions);
    }

    public function test_recherche_libre_sur_action(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $admin  = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'company_admin', 'is_active' => true]);

        $this->seedLog($tenant, $admin, 'incident_created');
        $this->seedLog($tenant, $admin, 'employee_updated', 'App\Models\Employee');

        $data = $this->actingAs($admin)->getJson('/api/v1/audit?search=incident')->assertOk()->json('data');

        $this->assertSame(['incident_created'], collect($data)->pluck('action')->all());
    }
}
