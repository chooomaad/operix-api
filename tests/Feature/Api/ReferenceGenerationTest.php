<?php

namespace Tests\Feature\Api;

use App\Models\SafetyIncident;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Génération des références séquentielles (INC-YYYY-NNNN) — correctness sous
 * charge (phase E).
 *
 * Le test de charge a révélé deux défauts de production sur la création
 * concurrente d'incidents :
 *  1. la référence était dérivée d'un count()+1 → deux requêtes simultanées
 *     forgeaient la même référence → violation d'unicité (500) ;
 *  2. l'extraction du max se faisait par tri LEXICAL → au-delà de 9999,
 *     « ...-9999 » l'emportait sur « ...-10000 », régénérant 10000 à l'infini.
 *
 * Corrigé par un verrou consultatif PostgreSQL (sérialise la génération) et un
 * max NUMÉRIQUE. Ces tests verrouillent la correction.
 */
class ReferenceGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function agent(Tenant $tenant): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'agent',
            'is_active' => true,
        ]);
    }

    private function payload(): array
    {
        return [
            'date'        => '2026-08-30',
            'location'    => 'Quai',
            'type'        => 'FAC',
            'severity'    => 'low',
            'description' => 'Reference generation test incident.',
        ];
    }

    public function test_les_references_sont_uniques_et_sequentielles(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $agent  = $this->agent($tenant);

        $refs = [];
        for ($i = 0; $i < 5; $i++) {
            $refs[] = $this->actingAs($agent)
                ->postJson('/api/v1/incidents', $this->payload())
                ->assertStatus(201)
                ->json('reference');
        }

        // Toutes distinctes, et strictement séquentielles à partir de 0001.
        $this->assertSame($refs, array_unique($refs));
        $year = date('Y');
        $this->assertSame([
            "INC-{$year}-0001", "INC-{$year}-0002", "INC-{$year}-0003",
            "INC-{$year}-0004", "INC-{$year}-0005",
        ], $refs);
    }

    public function test_le_numero_franchit_correctement_le_cap_de_9999(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $agent  = $this->agent($tenant);
        $year   = date('Y');

        // Pré-insère une référence à 9999 dans CE tenant (hors scope pour poser
        // tenant_id explicitement, comme le contrôleur depuis le contexte).
        app(TenantContext::class)->runWithoutScope(function () use ($tenant, $agent, $year) {
            app(TenantContext::class)->set($tenant->id);
            try {
                $inc = new SafetyIncident($this->payload());
                $inc->reference   = "INC-{$year}-9999";
                $inc->reported_by = $agent->id;
                $inc->save();
            } finally {
                app(TenantContext::class)->clear();
            }
        });

        // Le suivant doit être 10000 (max numérique + 1), PAS 10000-en-boucle.
        $r1 = $this->actingAs($agent)->postJson('/api/v1/incidents', $this->payload())
            ->assertStatus(201)->json('reference');
        $this->assertSame("INC-{$year}-10000", $r1);

        // Et le suivant 10001 : le tri lexical aurait régénéré 10000 (bug corrigé).
        $r2 = $this->actingAs($agent)->postJson('/api/v1/incidents', $this->payload())
            ->assertStatus(201)->json('reference');
        $this->assertSame("INC-{$year}-10001", $r2);
    }

    public function test_les_references_sont_scindees_par_tenant(): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);

        $refA = $this->actingAs($this->agent($tenantA))
            ->postJson('/api/v1/incidents', $this->payload())->json('reference');
        $refB = $this->actingAs($this->agent($tenantB))
            ->postJson('/api/v1/incidents', $this->payload())->json('reference');

        // Chaque entreprise a sa propre séquence : les deux commencent à 0001.
        $year = date('Y');
        $this->assertSame("INC-{$year}-0001", $refA);
        $this->assertSame("INC-{$year}-0001", $refB);
    }
}
