<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantBrandingTest extends TestCase
{
    use RefreshDatabase;

    private function adminFor(Tenant $tenant): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'company_admin',
            'is_active' => true,
        ]);
    }

    public function test_settings_and_auth_me_return_current_tenant_branding(): void
    {
        $tenant = Tenant::factory()->create([
            'name'          => 'Entreprise Alpha',
            'short_name'    => 'ALPHA',
            'primary_color' => '#123456',
            'locale'        => 'en',
            'timezone'      => 'UTC',
            'settings'      => ['sector' => 'port'],
        ]);
        $admin = $this->adminFor($tenant);

        $this->actingAs($admin)
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('name', 'Entreprise Alpha')
            ->assertJsonPath('primary_color', '#123456')
            ->assertJsonPath('settings.sector', 'port');

        $this->actingAs($admin)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('organisation.name', 'Entreprise Alpha')
            ->assertJsonPath('organisation.short_name', 'ALPHA')
            ->assertJsonPath('organisation.locale', 'en');
    }

    public function test_tenant_settings_cannot_leak_between_companies(): void
    {
        $tenantA = Tenant::factory()->create(['name' => 'Alpha', 'primary_color' => '#111111']);
        $tenantB = Tenant::factory()->create(['name' => 'Beta', 'primary_color' => '#222222']);
        $adminA  = $this->adminFor($tenantA);
        $adminB  = $this->adminFor($tenantB);

        $this->actingAs($adminA)
            ->putJson('/api/v1/settings', [
                'name'          => 'Alpha Updated',
                'primary_color' => '#abcdef',
            ])
            ->assertOk();

        $this->assertDatabaseHas('tenants', [
            'id'            => $tenantA->id,
            'name'          => 'Alpha Updated',
            'primary_color' => '#abcdef',
        ]);
        $this->assertDatabaseHas('tenants', [
            'id'            => $tenantB->id,
            'name'          => 'Beta',
            'primary_color' => '#222222',
        ]);

        $this->actingAs($adminB)
            ->getJson('/api/v1/settings')
            ->assertJsonPath('name', 'Beta')
            ->assertJsonPath('primary_color', '#222222');
    }

    public function test_branding_logo_is_stored_privately_under_current_tenant(): void
    {
        // Le logo est désormais stocké sur le disque PRIVÉ (tenant-media), préfixé tenant.
        Storage::fake('tenant-media');

        $tenantA = Tenant::factory()->create(['name' => 'Alpha']);
        $tenantB = Tenant::factory()->create(['name' => 'Beta']);
        $adminA  = $this->adminFor($tenantA);

        $this->actingAs($adminA)
            ->post('/api/v1/settings/logo', [
                'logo' => UploadedFile::fake()->image('alpha.png'),
            ])
            ->assertOk();

        $tenantA->refresh();
        $tenantB->refresh();

        $this->assertStringStartsWith("tenants/{$tenantA->id}/branding/", $tenantA->logo);
        $this->assertNull($tenantB->logo);
        Storage::disk('tenant-media')->assertExists($tenantA->logo);
    }
}
