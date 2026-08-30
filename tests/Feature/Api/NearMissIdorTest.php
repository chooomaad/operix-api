<?php

namespace Tests\Feature\Api;

use App\Models\SafetyNearMiss;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * IDOR cross-tenant sur les presqu'accidents (GET / PUT / DELETE).
 *
 * Elargit la couverture d'isolation existante (employes, incidents) a un autre
 * module HSSE : l'administrateur d'une entreprise ne doit jamais atteindre le
 * presqu'accident d'une AUTRE entreprise en devinant son identifiant. La
 * protection vient du TenantScope global (findOrFail scope -> 404), jamais du
 * frontend.
 */
class NearMissIdorTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Tenant $tenant): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'company_admin',
            'is_active' => true,
        ]);
    }

    private function nearMissFor(Tenant $tenant): SafetyNearMiss
    {
        // tenant_id est volontairement HORS fillable (protection mass-assignment) :
        // on l'assigne explicitement, exactement comme le controleur le fait
        // depuis le contexte tenant.
        $nm = new SafetyNearMiss([
            'reference'   => 'NM-' . $tenant->id . '-001',
            'date'        => now()->toDateString(),
            'location'    => 'Quai',
            'severity'    => 'medium',
            'description' => 'Palette instable',
            'status'      => 'open',
        ]);
        $nm->tenant_id = $tenant->id;
        $nm->save();

        return $nm;
    }

    public function test_un_admin_lit_son_presque_accident_mais_pas_celui_d_un_autre_tenant(): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);
        $adminA  = $this->admin($tenantA);

        $nmA = $this->nearMissFor($tenantA);
        $nmB = $this->nearMissFor($tenantB);

        // Sa propre ressource : OK.
        $this->actingAs($adminA)->getJson("/api/v1/near-miss/{$nmA->id}")->assertOk();

        // Celle d'une autre entreprise : introuvable (le scope filtre avant tout).
        $this->actingAs($adminA)->getJson("/api/v1/near-miss/{$nmB->id}")->assertStatus(404);
    }

    public function test_un_admin_ne_peut_pas_modifier_le_presque_accident_d_un_autre_tenant(): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);
        $adminA  = $this->admin($tenantA);
        $nmB     = $this->nearMissFor($tenantB);

        $this->actingAs($adminA)
            ->putJson("/api/v1/near-miss/{$nmB->id}", ['description' => 'pirate'])
            ->assertStatus(404);

        // La ressource de B est inchangee.
        $this->assertSame(
            'Palette instable',
            SafetyNearMiss::withoutGlobalScopes()->find($nmB->id)->description,
        );
    }

    public function test_un_admin_ne_peut_pas_supprimer_le_presque_accident_d_un_autre_tenant(): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);
        $adminA  = $this->admin($tenantA);
        $nmB     = $this->nearMissFor($tenantB);

        $this->actingAs($adminA)
            ->deleteJson("/api/v1/near-miss/{$nmB->id}")
            ->assertStatus(404);

        // La ressource de B existe toujours.
        $this->assertNotNull(SafetyNearMiss::withoutGlobalScopes()->find($nmB->id));
    }
}
