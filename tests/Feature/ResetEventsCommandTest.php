<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\SafetyIncident;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResetEventsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_vide_les_evenements_mais_preserve_comptes_et_employes(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $user   = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'company_admin', 'is_active' => true]);

        $employee = app(TenantContext::class)->runWithoutScope(function () use ($tenant) {
            app(TenantContext::class)->set($tenant->id);
            try { return Employee::factory()->create(['tenant_id' => $tenant->id]); }
            finally { app(TenantContext::class)->clear(); }
        });

        // Données d'événement à supprimer
        app(TenantContext::class)->runWithoutScope(function () use ($tenant) {
            app(TenantContext::class)->set($tenant->id);
            try {
                SafetyIncident::create([
                    'reference' => 'INC-2026-0001', 'date' => '2026-08-30', 'location' => 'Quai',
                    'type' => 'LTI', 'severity' => 'low', 'description' => 'test', 'status' => 'open',
                ]);
            } finally { app(TenantContext::class)->clear(); }
        });
        DB::table('activity_logs')->insert([
            'user_id' => $user->id, 'action' => 'incident_created', 'model_type' => SafetyIncident::class,
            'model_id' => 1, 'tenant_id' => $tenant->id, 'created_at' => now(),
        ]);

        $this->assertSame(1, DB::table('safety_incidents')->count());
        $this->assertGreaterThan(0, DB::table('activity_logs')->count());

        $this->artisan('operix:reset-events', ['--force' => true])->assertSuccessful();

        // Événements vidés
        $this->assertSame(0, DB::table('safety_incidents')->count());
        $this->assertSame(0, DB::table('activity_logs')->count());

        // Identités préservées
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('employees', ['id' => $employee->id]);
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
        $this->assertGreaterThan(0, DB::table('roles')->count());
        $this->assertGreaterThan(0, DB::table('permissions')->count());
    }
}
