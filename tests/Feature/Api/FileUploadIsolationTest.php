<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uploads par-champ (photo employé, etc.) : stockage privé préfixé tenant + service
 * uniquement via URL signée (prompt maître §18).
 */
class FileUploadIsolationTest extends TestCase
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

    public function test_employee_photo_is_stored_privately_under_tenant_and_served_via_signed_url(): void
    {
        Storage::fake('tenant-media');

        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $adminA  = $this->admin($tenantA);

        $response = $this->actingAs($adminA)->postJson('/api/v1/employees', [
            'matricule'     => 'EMP-PHOTO-1',
            'nom'           => 'Nom',
            'prenom'        => 'Prenom',
            'poste'         => 'Technicien',
            'type_contrat'  => 'CDI',
            'date_embauche' => '2024-01-01',
            'photo'         => UploadedFile::fake()->image('photo.jpg'),
        ])->assertStatus(201);

        $path     = $response->json('photo');
        $signedUrl = $response->json('photo_url');

        // Stockage privé, préfixé par tenant.
        $this->assertStringStartsWith("tenants/{$tenantA->id}/employees/", $path);
        Storage::disk('tenant-media')->assertExists($path);

        // Le fichier n'est PAS accessible sans signature valide.
        $this->getJson("/api/v1/files/serve?path={$path}")->assertStatus(403);

        // Un chemin hors de l'espace tenants/ est refusé (anti-traversée) même signé côté forme.
        $this->getJson('/api/v1/files/serve?path=../.env')->assertStatus(403);

        // L'URL signée émise pour A sert bien le fichier.
        $this->get($signedUrl)->assertStatus(200);
    }
}
