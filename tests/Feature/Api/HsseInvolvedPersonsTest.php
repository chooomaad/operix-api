<?php

namespace Tests\Feature\Api;

use App\Models\Breach;
use App\Models\Employee;
use App\Models\EnvironmentReport;
use App\Models\SafetyIncident;
use App\Models\SafetyNearMiss;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Comportement métier COMMUN aux 4 modules HSSE (Incident, Near Miss,
 * Breach of Process, Environment) pour les personnes impliquées.
 *
 * Les 4 modules stockent les personnes dans une colonne jsonb `employees`
 * (tableau d'identifiants). Il n'y a donc PAS de table pivot : remplacer le
 * tableau remplace la relation, et supprimer la ligne supprime la relation.
 * Ces tests garantissent qu'aucune relation fantôme ni doublon n'apparaît, que
 * l'historique employé suit, et que l'isolation tenant est appliquée dès la
 * validation — de façon identique pour les 4 modules.
 */
class HsseInvolvedPersonsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0:string,1:string,2:array<string,mixed>,3:array<string,mixed>}> */
    public static function modules(): array
    {
        return [
            // clé => [endpoint, historyKey, champs create spécifiques, payload close]
            'incident'    => ['incidents',   'incidents',   ['type' => 'LTI', 'severity' => 'low'],    ['root_cause' => 'Cause', 'corrective_action' => 'Action']],
            'near_miss'   => ['near-miss',   'near_miss',   ['severity' => 'low'],                      ['corrective_action' => 'Action']],
            'breach'      => ['breaches',    'breaches',    ['type' => 'EPI', 'severity' => 'high'],    ['corrective_action' => 'Action']],
            'environment' => ['environment', 'environment', ['type' => 'waste', 'severity' => 'medium'],['corrective_action' => 'Action']],
        ];
    }

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

    private function basePayload(array $extra): array
    {
        return array_merge([
            'date'        => '2026-08-31',
            'location'    => 'Quai 3',
            'description' => 'Événement de test.',
        ], $extra);
    }

    private function history(User $admin, int $empId, string $key): array
    {
        return $this->actingAs($admin)->getJson("/api/v1/employees/{$empId}/history")->json($key) ?? [];
    }

    private function storedEmployees(string $endpoint, int $id): array
    {
        $model = [
            'incidents'   => SafetyIncident::class,
            'near-miss'   => SafetyNearMiss::class,
            'breaches'    => Breach::class,
            'environment' => EnvironmentReport::class,
        ][$endpoint];
        return $model::withoutGlobalScopes()->find($id)->employees ?? [];
    }

    #[DataProvider('modules')]
    public function test_cycle_complet_personnes_impliquees(string $endpoint, string $historyKey, array $fields, array $closePayload): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = $this->admin($t);
        [$a, $b, $c, $d] = [$this->emp($t), $this->emp($t), $this->emp($t), $this->emp($t)];

        // ── CREATE avec A, B, C ─────────────────────────────────────────────
        $id = $this->actingAs($admin)->postJson("/api/v1/{$endpoint}", $this->basePayload($fields) + [
            'employees' => [$a->id, $b->id, $c->id],
        ])->assertStatus(201)->json('id');

        $this->assertEqualsCanonicalizing([$a->id, $b->id, $c->id], $this->storedEmployees($endpoint, $id));
        $this->assertCount(1, $this->history($admin, $a->id, $historyKey));
        $this->assertCount(1, $this->history($admin, $b->id, $historyKey));
        $this->assertCount(1, $this->history($admin, $c->id, $historyKey));
        $this->assertCount(0, $this->history($admin, $d->id, $historyKey));

        // ── EDIT : A,B,C → B,C,D ────────────────────────────────────────────
        $this->actingAs($admin)->putJson("/api/v1/{$endpoint}/{$id}", ['employees' => [$b->id, $c->id, $d->id]])
            ->assertOk();

        $after = $this->storedEmployees($endpoint, $id);
        $this->assertEqualsCanonicalizing([$b->id, $c->id, $d->id], $after);
        $this->assertNotContains($a->id, $after);          // A retiré, aucune relation fantôme
        $this->assertCount(3, $after);                     // aucun doublon

        $this->assertCount(0, $this->history($admin, $a->id, $historyKey)); // A n'a plus l'événement
        $this->assertCount(1, $this->history($admin, $b->id, $historyKey)); // B conservé
        $this->assertCount(1, $this->history($admin, $c->id, $historyKey)); // C conservé
        $this->assertCount(1, $this->history($admin, $d->id, $historyKey)); // D ajouté

        // ── REFRESH : relire ne duplique pas les relations ──────────────────
        $reread = $this->actingAs($admin)->getJson("/api/v1/{$endpoint}/{$id}")->assertOk()->json('employees');
        $this->assertEqualsCanonicalizing([$b->id, $c->id, $d->id], $reread);
        $this->assertCount(3, $reread);

        // ── CLOSE : l'événement reste dans l'historique, statut = closed ─────
        $this->actingAs($admin)->postJson("/api/v1/{$endpoint}/{$id}/close", $closePayload)
            ->assertOk()->assertJsonPath('status', 'closed');
        $this->assertCount(1, $this->history($admin, $b->id, $historyKey)); // toujours présent après fermeture

        // ── DELETE : disparaît de l'historique de TOUTES les personnes ──────
        $this->actingAs($admin)->deleteJson("/api/v1/{$endpoint}/{$id}")->assertOk();
        $this->assertCount(0, $this->history($admin, $b->id, $historyKey));
        $this->assertCount(0, $this->history($admin, $c->id, $historyKey));
        $this->assertCount(0, $this->history($admin, $d->id, $historyKey));
    }

    #[DataProvider('modules')]
    public function test_isolation_tenant_refuse_employe_d_un_autre_tenant(string $endpoint, string $historyKey, array $fields, array $closePayload): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);
        $adminA  = $this->admin($tenantA);
        $empB    = $this->emp($tenantB); // employé d'un AUTRE tenant

        $this->actingAs($adminA)->postJson("/api/v1/{$endpoint}", $this->basePayload($fields) + [
            'employees' => [$empB->id],
        ])->assertStatus(422)->assertJsonValidationErrors('employees.0');
    }
}
