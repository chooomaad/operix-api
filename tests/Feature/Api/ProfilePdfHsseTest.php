<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le PDF de profil employé doit contenir tout l'historique HSSE (incidents,
 * near miss, breach of process, environnement) avec référence, date, lieu, etc.
 */
class ProfilePdfHsseTest extends TestCase
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

    public function test_le_pdf_profil_contient_l_historique_hsse(): void
    {
        $t     = Tenant::factory()->create(['status' => 'active']);
        $admin = $this->admin($t);
        $emp   = $this->emp($t);

        // Incident impliquant l'employé
        $incRef = $this->actingAs($admin)->postJson('/api/v1/incidents', [
            'date' => '2026-08-30', 'time' => '14:30', 'location' => 'Quai 3',
            'type' => 'LTI', 'severity' => 'high', 'description' => 'Glissade sur le quai.',
            'involved_people' => [['type' => 'employee', 'id' => $emp->id]],
        ])->assertStatus(201)->json('reference');

        // Breach impliquant l'employé
        $brRef = $this->actingAs($admin)->postJson('/api/v1/breaches', [
            'date' => '2026-08-28', 'location' => 'Entrepôt', 'type' => 'EPI',
            'severity' => 'medium', 'description' => 'EPI non porté.',
            'involved_people' => [['type' => 'employee', 'id' => $emp->id]],
        ])->assertStatus(201)->json('reference');

        // 1) Le endpoint PDF répond bien (pas de 500 dû aux nouvelles requêtes/blade)
        $res = $this->actingAs($admin)->get("/api/v1/reports/employees/{$emp->id}/profile");
        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
        $this->assertGreaterThan(1000, strlen($res->getContent())); // PDF non vide

        // 2) Le rendu HTML de la vue contient l'historique HSSE et les références
        $html = view('pdf.employee_profile', [
            'title' => 'Profil', 'orgName' => 'TCN', 'orgShort' => 'TCN',
            'orgLogo' => null, 'brandColor' => '#0f2847', 'employee' => $emp,
            'incidents'   => \App\Models\SafetyIncident::whereRaw('involved_people @> ?::jsonb', [json_encode([['type' => 'employee', 'id' => $emp->id]])])->get(),
            'nearMiss'    => \App\Models\SafetyNearMiss::whereRaw('involved_people @> ?::jsonb', [json_encode([['type' => 'employee', 'id' => $emp->id]])])->get(),
            'breaches'    => \App\Models\Breach::whereRaw('involved_people @> ?::jsonb', [json_encode([['type' => 'employee', 'id' => $emp->id]])])->get(),
            'environment' => \App\Models\EnvironmentReport::whereRaw('involved_people @> ?::jsonb', [json_encode([['type' => 'employee', 'id' => $emp->id]])])->get(),
            'formations' => collect(), 'certifications' => collect(), 'medicalVisits' => collect(),
        ])->render();

        $this->assertStringContainsString('Historique HSSE', $html);
        $this->assertStringContainsString($incRef, $html);
        $this->assertStringContainsString('Quai 3', $html);   // localisation
        $this->assertStringContainsString('14:30', $html);    // heure
        $this->assertStringContainsString($brRef, $html);
    }
}
