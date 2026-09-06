<?php

namespace Tests\Feature\Api;

use App\Models\Intern;
use App\Models\PpeIssuance;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Dotation EPI (employés uniquement) : plusieurs articles + plusieurs catégories
 * par remise, quantité dérivée du nombre de catégories.
 */
class PpeIssuanceTest extends TestCase
{
    use RefreshDatabase;

    private function adminAndEmployee(): array
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = User::factory()->create(['tenant_id' => $t->id, 'role' => 'company_admin', 'is_active' => true]);
        $empId = $this->actingAs($admin)->postJson('/api/v1/employees', [
            'matricule'     => 'EMP-EPI-1',
            'nom'           => 'Ba',
            'prenom'        => 'Amadou',
            'poste'         => 'Docker',
            'type_contrat'  => 'CDI',
            'date_embauche' => '2024-01-01',
        ])->assertStatus(201)->json('id');

        return [$t, $admin, $empId];
    }

    public function test_create_epi_multi_and_quantity_is_category_count(): void
    {
        [$t, $admin, $empId] = $this->adminAndEmployee();
        $base = "/api/v1/people/employee/{$empId}/epi";

        $id = $this->actingAs($admin)->postJson($base, [
            'items'      => ['helmet', 'gloves', 'safety_boots'],
            'categories' => ['head', 'hands', 'feet'],
            'issued_at'  => '2026-09-01',
        ])->assertStatus(201)->json('id');

        $row = PpeIssuance::withoutGlobalScopes()->find($id);
        $this->assertSame('employee', $row->person_type);
        $this->assertEqualsCanonicalizing(['helmet', 'gloves', 'safety_boots'], $row->items);
        $this->assertEqualsCanonicalizing(['head', 'hands', 'feet'], $row->categories);
        // head + hands + feet → 3
        $this->assertSame(3, $row->quantity);
        $this->assertSame('neuf', $row->condition);

        $this->actingAs($admin)->getJson($base)->assertOk()->assertJsonCount(1);
        $this->actingAs($admin)->deleteJson("{$base}/{$id}")->assertOk();
        $this->actingAs($admin)->getJson($base)->assertOk()->assertJsonCount(0);
    }

    public function test_items_and_categories_required(): void
    {
        [, $admin, $empId] = $this->adminAndEmployee();
        $base = "/api/v1/people/employee/{$empId}/epi";

        $this->actingAs($admin)->postJson($base, ['issued_at' => '2026-09-01'])
            ->assertStatus(422)->assertJsonValidationErrors(['items', 'categories']);

        $this->actingAs($admin)->postJson($base, [
            'items' => ['helmet'], 'categories' => [], 'issued_at' => '2026-09-01',
        ])->assertStatus(422)->assertJsonValidationErrors(['categories']);
    }

    public function test_epi_is_forbidden_for_non_employee(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = User::factory()->create(['tenant_id' => $t->id, 'role' => 'company_admin', 'is_active' => true]);

        $intern = app(TenantContext::class)->runWithoutScope(function () use ($t) {
            app(TenantContext::class)->set($t->id);
            try { return Intern::create(['tenant_id' => $t->id, 'reference' => 'INT-2026-0001', 'nom' => 'X', 'prenom' => 'Y', 'status' => 'active', 'is_active' => true]); }
            finally { app(TenantContext::class)->clear(); }
        });

        $this->actingAs($admin)->getJson("/api/v1/people/intern/{$intern->id}/epi")->assertStatus(404);
        $this->actingAs($admin)->postJson("/api/v1/people/intern/{$intern->id}/epi", [
            'items' => ['helmet'], 'categories' => ['head'], 'issued_at' => '2026-09-01',
        ])->assertStatus(404);
    }

    public function test_justificatif_pdf_stored_privately(): void
    {
        Storage::fake('tenant-media');
        [$t, $admin, $empId] = $this->adminAndEmployee();

        $res = $this->actingAs($admin)->post("/api/v1/people/employee/{$empId}/epi", [
            'items'      => ['safety_boots'],
            'categories' => ['feet'],
            'issued_at'  => '2026-09-01',
            'image'      => UploadedFile::fake()->create('bon-remise.pdf', 120, 'application/pdf'),
        ])->assertStatus(201);

        $path = $res->json('document');
        $this->assertStringStartsWith("tenants/{$t->id}/epi/", $path);
        Storage::disk('tenant-media')->assertExists($path);
        $this->assertNotNull($res->json('image_url'));
    }
}
