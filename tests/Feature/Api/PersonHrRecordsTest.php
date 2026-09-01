<?php

namespace Tests\Feature\Api;

use App\Models\Certification;
use App\Models\Intern;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les dossiers RH (formations/certifications/visites médicales) sont rattachables
 * à toute personne — ici un stagiaire — comme pour les employés.
 */
class PersonHrRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_crud_dossiers_rh_pour_un_stagiaire(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = User::factory()->create(['tenant_id' => $t->id, 'role' => 'company_admin', 'is_active' => true]);
        $intern = app(TenantContext::class)->runWithoutScope(function () use ($t) {
            app(TenantContext::class)->set($t->id);
            try { return Intern::create(['tenant_id' => $t->id, 'reference' => 'INT-2026-0009', 'nom' => 'X', 'prenom' => 'Y', 'status' => 'active', 'is_active' => true]); }
            finally { app(TenantContext::class)->clear(); }
        });

        $base = "/api/v1/people/intern/{$intern->id}";

        // Certification (titre requis, plus de bug type)
        $cid = $this->actingAs($admin)->postJson("{$base}/certifications", [
            'titre' => 'HSE Basics', 'date_obtention' => '2026-09-01',
        ])->assertStatus(201)->json('id');

        // Formation (défauts type/statut)
        $this->actingAs($admin)->postJson("{$base}/formations", [
            'titre' => 'Secourisme', 'date_debut' => '2026-09-01',
        ])->assertStatus(201);

        // Visite médicale (défauts type/resultat)
        $this->actingAs($admin)->postJson("{$base}/medical-visits", [
            'date' => '2026-09-01',
        ])->assertStatus(201);

        // Bien rattachés au stagiaire (person_type/person_id)
        $cert = Certification::withoutGlobalScopes()->find($cid);
        $this->assertSame('intern', $cert->person_type);
        $this->assertSame($intern->id, $cert->person_id);

        // Liste renvoie la certif
        $this->actingAs($admin)->getJson("{$base}/certifications")->assertOk()->assertJsonCount(1);

        // Update + delete
        $this->actingAs($admin)->putJson("{$base}/certifications/{$cid}", ['titre' => 'HSE Advanced'])->assertOk();
        $this->actingAs($admin)->deleteJson("{$base}/certifications/{$cid}")->assertOk();
        $this->actingAs($admin)->getJson("{$base}/certifications")->assertOk()->assertJsonCount(0);
    }
}
