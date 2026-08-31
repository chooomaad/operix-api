<?php

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Training / Certification / Medical Visit doivent être tracés dans l'audit :
 * QUI a fait QUOI, POUR QUEL employé, QUAND — création, modification, suppression.
 */
class HrRecordsAuditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Tenant $t): User
    {
        return User::factory()->create(['tenant_id' => $t->id, 'role' => 'company_admin', 'is_active' => true, 'name' => 'Abdellahi Haiba']);
    }

    private function emp(Tenant $t): Employee
    {
        return app(TenantContext::class)->runWithoutScope(function () use ($t) {
            app(TenantContext::class)->set($t->id);
            try { return Employee::factory()->create(['tenant_id' => $t->id]); }
            finally { app(TenantContext::class)->clear(); }
        });
    }

    public function test_certification_create_update_delete_sont_auditees(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = $this->admin($t);
        $emp = $this->emp($t);

        // CREATE
        $id = $this->actingAs($admin)->postJson("/api/v1/employees/{$emp->id}/certifications", [
            'titre' => 'ATEX', 'date_obtention' => '2026-08-31',
        ])->assertStatus(201)->json('id');

        $log = ActivityLog::where('action', 'certification_created')->where('model_id', $id)->first();
        $this->assertNotNull($log, 'La création de certification doit être auditée.');
        $this->assertSame($admin->id, $log->user_id);                 // QUI
        $this->assertEquals($emp->id, $log->new_values['employee_id']); // POUR QUI
        $this->assertNotNull($log->created_at);                        // QUAND

        // UPDATE
        $this->actingAs($admin)->putJson("/api/v1/employees/{$emp->id}/certifications/{$id}", [
            'titre' => 'ATEX Niveau 2',
        ])->assertOk();
        $upd = ActivityLog::where('action', 'certification_updated')->where('model_id', $id)->first();
        $this->assertNotNull($upd);
        $this->assertEquals('ATEX Niveau 2', $upd->new_values['titre']);   // NOUVELLE valeur
        $this->assertEquals('ATEX', $upd->old_values['titre']);            // ANCIENNE valeur

        // DELETE
        $this->actingAs($admin)->deleteJson("/api/v1/employees/{$emp->id}/certifications/{$id}")->assertOk();
        $this->assertNotNull(ActivityLog::where('action', 'certification_deleted')->where('model_id', $id)->first());
    }

    public function test_training_et_visite_medicale_sont_auditees(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = $this->admin($t);
        $emp = $this->emp($t);

        $fId = $this->actingAs($admin)->postJson("/api/v1/employees/{$emp->id}/formations", [
            'titre' => 'Secourisme', 'date_debut' => '2026-08-01',
        ])->assertStatus(201)->json('id');
        $this->assertNotNull(ActivityLog::where('action', 'training_created')->where('model_id', $fId)->first());

        $mId = $this->actingAs($admin)->postJson("/api/v1/employees/{$emp->id}/medical-visits", [
            'date' => '2026-08-15',
        ])->assertStatus(201)->json('id');
        $this->assertNotNull(ActivityLog::where('action', 'medical_visit_created')->where('model_id', $mId)->first());
    }
}
