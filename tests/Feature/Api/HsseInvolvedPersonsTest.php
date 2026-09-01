<?php

namespace Tests\Feature\Api;

use App\Models\Breach;
use App\Models\Contractor;
use App\Models\ContractorEmployee;
use App\Models\Employee;
use App\Models\EnvironmentReport;
use App\Models\Intern;
use App\Models\SafetyIncident;
use App\Models\SafetyNearMiss;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Comportement COMMUN aux 4 modules HSSE pour les personnes impliquées, avec la
 * nouvelle abstraction « personne » (type + id) stockée en jsonb involved_people.
 * Une personne peut être employee / contractor / visitor / intern.
 */
class HsseInvolvedPersonsTest extends TestCase
{
    use RefreshDatabase;

    public static function modules(): array
    {
        return [
            'incident'    => ['incidents',   'incidents',   ['type' => 'LTI', 'severity' => 'low'],     ['root_cause' => 'C', 'corrective_action' => 'A']],
            'near_miss'   => ['near-miss',   'near_miss',   ['severity' => 'low'],                       ['corrective_action' => 'A']],
            'breach'      => ['breaches',    'breaches',    ['type' => 'EPI', 'severity' => 'high'],     ['corrective_action' => 'A']],
            'environment' => ['environment', 'environment', ['type' => 'waste', 'severity' => 'medium'], ['corrective_action' => 'A']],
        ];
    }

    private function admin(Tenant $t): User
    {
        return User::factory()->create(['tenant_id' => $t->id, 'role' => 'company_admin', 'is_active' => true]);
    }

    private function runInTenant(Tenant $t, callable $fn)
    {
        return app(TenantContext::class)->runWithoutScope(function () use ($t, $fn) {
            app(TenantContext::class)->set($t->id);
            try { return $fn(); }
            finally { app(TenantContext::class)->clear(); }
        });
    }

    private function emp(Tenant $t, array $a = []): Employee
    {
        return $this->runInTenant($t, fn () => Employee::factory()->create(array_merge(['tenant_id' => $t->id], $a)));
    }

    private function contractorPerson(Tenant $t, array $a = []): ContractorEmployee
    {
        return $this->runInTenant($t, function () use ($t, $a) {
            $c = Contractor::create(['tenant_id' => $t->id, 'company_name' => 'ABC Construction', 'activite' => 'BTP', 'status' => 'active']);
            return ContractorEmployee::create(array_merge([
                'tenant_id' => $t->id, 'contractor_id' => $c->id, 'nom' => 'X', 'prenom' => 'Y', 'is_active' => true,
            ], $a));
        });
    }

    private function visitorPerson(Tenant $t, array $a = []): Visitor
    {
        return $this->runInTenant($t, fn () => Visitor::create(array_merge([
            'tenant_id' => $t->id, 'nom' => 'X', 'prenom' => 'Y', 'status' => 'in',
            'motif' => 'Réunion', 'personne_visitee' => 'Direction', 'checked_in_at' => now(),
        ], $a)));
    }

    private function internPerson(Tenant $t, array $a = []): Intern
    {
        return $this->runInTenant($t, fn () => Intern::create(array_merge([
            'tenant_id' => $t->id, 'reference' => 'INT-2026-0001', 'nom' => 'X', 'prenom' => 'Y', 'status' => 'active', 'is_active' => true,
        ], $a)));
    }

    private function base(array $extra): array
    {
        return array_merge(['date' => '2026-08-31', 'location' => 'Quai', 'description' => 'Test.'], $extra);
    }

    private function ref(string $type, int $id): array { return ['type' => $type, 'id' => $id]; }

    /** Personnes stockées (brut jsonb) sur l'événement. */
    private function stored(string $endpoint, int $id): array
    {
        $model = ['incidents' => SafetyIncident::class, 'near-miss' => SafetyNearMiss::class,
                  'breaches' => Breach::class, 'environment' => EnvironmentReport::class][$endpoint];
        return $model::withoutGlobalScopes()->find($id)->involved_people ?? [];
    }

    private function history(User $admin, string $type, int $id, string $key): array
    {
        return $this->actingAs($admin)->getJson("/api/v1/people/{$type}/{$id}/history")->json($key) ?? [];
    }

    #[DataProvider('modules')]
    public function test_cycle_complet_employes(string $endpoint, string $historyKey, array $fields, array $close): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = $this->admin($t);
        [$a, $b, $c, $d] = [$this->emp($t), $this->emp($t), $this->emp($t), $this->emp($t)];

        // CREATE A,B,C
        $id = $this->actingAs($admin)->postJson("/api/v1/{$endpoint}", $this->base($fields) + [
            'involved_people' => [$this->ref('employee', $a->id), $this->ref('employee', $b->id), $this->ref('employee', $c->id)],
        ])->assertStatus(201)->json('id');

        $this->assertCount(3, $this->stored($endpoint, $id));
        $this->assertCount(1, $this->history($admin, 'employee', $a->id, $historyKey));
        $this->assertCount(0, $this->history($admin, 'employee', $d->id, $historyKey));

        // EDIT A,B,C -> B,C,D
        $this->actingAs($admin)->putJson("/api/v1/{$endpoint}/{$id}", [
            'involved_people' => [$this->ref('employee', $b->id), $this->ref('employee', $c->id), $this->ref('employee', $d->id)],
        ])->assertOk();

        $this->assertCount(3, $this->stored($endpoint, $id)); // pas de doublon
        $this->assertCount(0, $this->history($admin, 'employee', $a->id, $historyKey)); // A retiré
        $this->assertCount(1, $this->history($admin, 'employee', $d->id, $historyKey)); // D ajouté

        // CLOSE : reste dans l'historique
        $this->actingAs($admin)->postJson("/api/v1/{$endpoint}/{$id}/close", $close)->assertOk()->assertJsonPath('status', 'closed');
        $this->assertCount(1, $this->history($admin, 'employee', $b->id, $historyKey));

        // DELETE : disparaît de tous
        $this->actingAs($admin)->deleteJson("/api/v1/{$endpoint}/{$id}")->assertOk();
        $this->assertCount(0, $this->history($admin, 'employee', $b->id, $historyKey));
        $this->assertCount(0, $this->history($admin, 'employee', $d->id, $historyKey));
    }

    /**
     * TEST CRITIQUE : 4 personnes de types différents mais MÊME NOM sont 4
     * personnes distinctes. Édition et suppression sans confusion ni fantôme.
     */
    public function test_meme_nom_types_differents_sont_distincts(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = $this->admin($t);

        $emp = $this->emp($t, ['nom' => 'Ahmed', 'prenom' => 'Mohamed', 'matricule' => '100289']);
        $con = $this->contractorPerson($t, ['nom' => 'Ahmed', 'prenom' => 'Mohamed', 'badge_number' => 'CONT-012']);
        $vis = $this->visitorPerson($t, ['nom' => 'Ahmed', 'prenom' => 'Mohamed', 'badge_number' => 'VIS-2026-044']);
        $int = $this->internPerson($t, ['nom' => 'Ahmed', 'prenom' => 'Mohamed', 'reference' => 'INT-2026-003']);

        // CREATE avec les 4 (même nom)
        $id = $this->actingAs($admin)->postJson('/api/v1/incidents', $this->base(['type' => 'LTI', 'severity' => 'low']) + [
            'involved_people' => [
                $this->ref('employee', $emp->id), $this->ref('contractor', $con->id),
                $this->ref('visitor', $vis->id), $this->ref('intern', $int->id),
            ],
        ])->assertStatus(201)->json('id');

        // 4 personnes DISTINCTES enregistrées
        $this->assertCount(4, $this->stored('incidents', $id));
        $this->assertCount(1, $this->history($admin, 'employee', $emp->id, 'incidents'));
        $this->assertCount(1, $this->history($admin, 'contractor', $con->id, 'incidents'));
        $this->assertCount(1, $this->history($admin, 'visitor', $vis->id, 'incidents'));
        $this->assertCount(1, $this->history($admin, 'intern', $int->id, 'incidents'));

        // EDIT : garder employee + visitor uniquement
        $this->actingAs($admin)->putJson("/api/v1/incidents/{$id}", [
            'involved_people' => [$this->ref('employee', $emp->id), $this->ref('visitor', $vis->id)],
        ])->assertOk();

        $this->assertCount(1, $this->history($admin, 'employee', $emp->id, 'incidents'));   // gardé
        $this->assertCount(1, $this->history($admin, 'visitor', $vis->id, 'incidents'));    // gardé
        $this->assertCount(0, $this->history($admin, 'contractor', $con->id, 'incidents')); // retiré
        $this->assertCount(0, $this->history($admin, 'intern', $int->id, 'incidents'));     // retiré

        // DELETE : plus aucune relation
        $this->actingAs($admin)->deleteJson("/api/v1/incidents/{$id}")->assertOk();
        $this->assertCount(0, $this->history($admin, 'employee', $emp->id, 'incidents'));
        $this->assertCount(0, $this->history($admin, 'visitor', $vis->id, 'incidents'));
    }

    #[DataProvider('modules')]
    public function test_isolation_tenant(string $endpoint, string $historyKey, array $fields, array $close): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);
        $adminA  = $this->admin($tenantA);
        $empB    = $this->emp($tenantB); // employé d'un AUTRE tenant

        $this->actingAs($adminA)->postJson("/api/v1/{$endpoint}", $this->base($fields) + [
            'involved_people' => [$this->ref('employee', $empB->id)],
        ])->assertStatus(422)->assertJsonValidationErrors('involved_people');
    }
}
