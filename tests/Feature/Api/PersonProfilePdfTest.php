<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\Intern;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Profil PDF pour une personne non-employé (ici un stagiaire impliqué). */
class PersonProfilePdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_profil_stagiaire_contient_son_historique(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = User::factory()->create(['tenant_id' => $t->id, 'role' => 'company_admin', 'is_active' => true]);

        $intern = app(TenantContext::class)->runWithoutScope(function () use ($t) {
            app(TenantContext::class)->set($t->id);
            try {
                return Intern::create(['tenant_id' => $t->id, 'reference' => 'INT-2026-0007', 'nom' => 'Elemine', 'prenom' => 'Choumad', 'status' => 'active', 'is_active' => true]);
            } finally { app(TenantContext::class)->clear(); }
        });

        // Incident impliquant le stagiaire
        $ref = $this->actingAs($admin)->postJson('/api/v1/incidents', [
            'date' => '2026-09-01', 'time' => '10:00', 'location' => 'Quai 5',
            'type' => 'LTI', 'severity' => 'high', 'description' => 'Test stagiaire.',
            'involved_people' => [['type' => 'intern', 'id' => $intern->id]],
        ])->assertStatus(201)->json('reference');

        // Le PDF profil du stagiaire répond et n'est pas vide
        $res = $this->actingAs($admin)->get("/api/v1/reports/people/intern/{$intern->id}/profile");
        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
        $this->assertGreaterThan(1000, strlen($res->getContent()));

        // L'historique de la personne renvoie bien l'incident
        $hist = $this->actingAs($admin)->getJson("/api/v1/people/intern/{$intern->id}/history")->assertOk();
        $this->assertCount(1, $hist->json('incidents'));
        $this->assertEquals($ref, $hist->json('incidents.0.reference'));
    }
}
