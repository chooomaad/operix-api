<?php

namespace Tests\Feature\Api;

use App\Models\PpeIssuance;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Dotation EPI rattachée à un employé via le système générique des dossiers RH.
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

    public function test_crud_epi_for_employee(): void
    {
        [$t, $admin, $empId] = $this->adminAndEmployee();
        $base = "/api/v1/people/employee/{$empId}/epi";

        $id = $this->actingAs($admin)->postJson($base, [
            'designation' => 'Casque de sécurité',
            'category'    => 'head',
            'size'        => 'L',
            'quantity'    => 2,
            'issued_at'   => '2026-09-01',
        ])->assertStatus(201)->json('id');

        $row = PpeIssuance::withoutGlobalScopes()->find($id);
        $this->assertSame('employee', $row->person_type);
        $this->assertSame($empId, $row->person_id);
        $this->assertSame(2, $row->quantity);
        $this->assertSame('neuf', $row->condition);

        $this->actingAs($admin)->getJson($base)->assertOk()->assertJsonCount(1);
        $this->actingAs($admin)->putJson("{$base}/{$id}", ['condition' => 'use'])->assertOk();
        $this->actingAs($admin)->deleteJson("{$base}/{$id}")->assertOk();
        $this->actingAs($admin)->getJson($base)->assertOk()->assertJsonCount(0);
    }

    public function test_designation_required(): void
    {
        [, $admin, $empId] = $this->adminAndEmployee();
        $this->actingAs($admin)->postJson("/api/v1/people/employee/{$empId}/epi", [
            'issued_at' => '2026-09-01',
        ])->assertStatus(422)->assertJsonValidationErrors(['designation']);
    }

    public function test_justificatif_pdf_stored_privately(): void
    {
        Storage::fake('tenant-media');
        [$t, $admin, $empId] = $this->adminAndEmployee();

        $res = $this->actingAs($admin)->post("/api/v1/people/employee/{$empId}/epi", [
            'designation' => 'Chaussures S3',
            'issued_at'   => '2026-09-01',
            'image'       => UploadedFile::fake()->create('bon-remise.pdf', 120, 'application/pdf'),
        ])->assertStatus(201);

        $path = $res->json('document');
        $this->assertStringStartsWith("tenants/{$t->id}/epi/", $path);
        Storage::disk('tenant-media')->assertExists($path);
        $this->assertNotNull($res->json('image_url'));
    }
}
