<?php

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\Employee;
use App\Models\SafetyIncident;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit automatique (qui / quoi / quand) de toute action metier, + notification
 * des responsables sur les actions importantes.
 */
class AuditObserverTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Tenant $tenant): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => 'company_admin', 'is_active' => true,
        ]);
    }

    public function test_la_creation_via_api_est_auditee_avec_auteur_et_horodatage(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $admin  = $this->admin($tenant);

        $id = $this->actingAs($admin)->postJson('/api/v1/incidents', [
            'date' => '2026-08-30', 'location' => 'Quai', 'type' => 'Fire',
            'severity' => 'low', 'description' => 'Audit create test incident.',
        ])->assertStatus(201)->json('id');

        $log = ActivityLog::withoutGlobalScopes()
            ->where('action', 'incident_created')->where('model_id', $id)->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);           // QUI
        $this->assertSame(SafetyIncident::class, $log->model_type); // QUOI
        $this->assertNotNull($log->created_at);                  // QUAND
        $this->assertSame($tenant->id, $log->tenant_id);         // scinde par tenant
    }

    public function test_la_modification_est_auditee_avec_les_changements(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $admin  = $this->admin($tenant);

        $id = $this->actingAs($admin)->postJson('/api/v1/incidents', [
            'date' => '2026-08-30', 'location' => 'A', 'type' => 'Fire',
            'severity' => 'low', 'description' => 'Avant modification.',
        ])->json('id');

        $this->actingAs($admin)->putJson("/api/v1/incidents/{$id}", ['location' => 'B'])
            ->assertStatus(200);

        $log = ActivityLog::withoutGlobalScopes()
            ->where('action', 'incident_updated')->where('model_id', $id)->latest('created_at')->first();

        $this->assertNotNull($log);
        $this->assertSame('B', $log->new_values['location'] ?? null);
        $this->assertSame('A', $log->old_values['location'] ?? null);
    }

    public function test_la_suppression_est_auditee(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $admin  = $this->admin($tenant);

        $id = $this->actingAs($admin)->postJson('/api/v1/incidents', [
            'date' => '2026-08-30', 'location' => 'A', 'type' => 'Fire',
            'severity' => 'low', 'description' => 'A supprimer.',
        ])->json('id');

        $this->actingAs($admin)->deleteJson("/api/v1/incidents/{$id}")->assertStatus(200);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'incident_deleted', 'model_id' => $id, 'user_id' => $admin->id,
        ]);
    }

    public function test_les_secrets_sont_redactes_dans_le_journal(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $admin  = $this->admin($tenant);

        $newId = $this->actingAs($admin)->postJson('/api/v1/users', [
            'name' => 'Nouveau', 'email' => 'nouveau@tcn.mr', 'role' => 'agent', 'password' => 'secret12',
        ])->assertCreated()->json('id');

        $log = ActivityLog::withoutGlobalScopes()
            ->where('action', 'user_created')->where('model_id', $newId)->first();

        $this->assertNotNull($log);
        // Le mot de passe (meme hache) ne doit jamais apparaitre en clair.
        $this->assertSame('****', $log->new_values['password'] ?? null);
        $this->assertStringNotContainsString('secret12', json_encode($log->new_values));
    }

    public function test_pas_d_audit_sans_utilisateur_authentifie(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        // Creation hors requete HTTP (factory + contexte) : aucune ligne d'audit.
        app(TenantContext::class)->runWithoutScope(function () use ($tenant) {
            app(TenantContext::class)->set($tenant->id);
            try { Employee::factory()->create(['tenant_id' => $tenant->id]); }
            finally { app(TenantContext::class)->clear(); }
        });

        $this->assertSame(0, ActivityLog::withoutGlobalScopes()->count());
    }

    public function test_une_action_importante_notifie_les_responsables_pas_l_auteur(): void
    {
        $tenant  = Tenant::factory()->create(['status' => 'active']);
        $admin   = $this->admin($tenant); // acteur (audit.view)
        $manager = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => 'hsse_manager', 'is_active' => true,
        ]); // autre detenteur d'audit.view

        $this->actingAs($admin)->postJson('/api/v1/employees', [
            'nom' => 'Test', 'prenom' => 'Audit', 'matricule' => 'AUD-001',
            'poste' => 'Operateur', 'type_contrat' => 'CDI',
        ])->assertCreated();

        // Le manager est notifie ; l'auteur (admin) ne se notifie pas lui-meme.
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $manager->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $admin->id]);
    }

    public function test_la_creation_hse_ne_double_pas_la_notification_d_audit(): void
    {
        // incident cree : notifie par le flux HSE, PAS en plus par l'audit.
        $tenant  = Tenant::factory()->create(['status' => 'active']);
        $admin   = $this->admin($tenant);
        $manager = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => 'hsse_manager', 'is_active' => true,
        ]);

        $this->actingAs($admin)->postJson('/api/v1/incidents', [
            'date' => '2026-08-30', 'location' => 'X', 'type' => 'Fire',
            'severity' => 'high', 'description' => 'Incident notifie une seule fois.',
        ])->assertCreated();

        // Une seule notification pour le manager (celle du flux HSE), pas deux.
        $this->assertSame(1, AppNotification::withoutGlobalScopes()
            ->where('notifiable_id', $manager->id)->count());
    }
}
