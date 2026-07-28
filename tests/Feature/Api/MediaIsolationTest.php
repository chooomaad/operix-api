<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaIsolationTest extends TestCase
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

    public function test_media_is_tenant_scoped_and_served_only_via_signed_url(): void
    {
        Storage::fake('tenant-media');

        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);
        $adminA  = $this->admin($tenantA);
        $adminB  = $this->admin($tenantB);

        // Upload par A
        $upload = $this->actingAs($adminA)->postJson('/api/v1/media', [
            'file'       => UploadedFile::fake()->image('photo.jpg'),
            'model_type' => 'employee',
            'model_id'   => 1,
        ])->assertStatus(201);

        $mediaId  = $upload->json('id');
        $signedUrl = $upload->json('url');

        // Le fichier est bien stocké sous le préfixe tenant, sur le disque privé.
        $this->assertStringContainsString("tenants/{$tenantA->id}/", $upload->json('path'));

        // B ne voit pas les métadonnées du média de A (global scope → 404).
        $this->actingAs($adminB)
            ->getJson("/api/v1/media/{$mediaId}")
            ->assertStatus(404);

        // Téléchargement SANS signature valide → 403 (route `signed`).
        $this->getJson("/api/v1/media/{$mediaId}/download")
            ->assertStatus(403);

        // L'URL signée émise pour A permet de servir le fichier.
        $this->get($signedUrl)->assertStatus(200);
    }
}
