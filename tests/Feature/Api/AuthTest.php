<?php

namespace Tests\Feature\Api;

use App\Models\OtpToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function createTenantWithAdmin(): array
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $admin  = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'company_admin',
            'is_active' => true,
        ]);
        return [$tenant, $admin];
    }

    public function test_request_otp_requires_email(): void
    {
        $this->postJson('/api/v1/auth/request-otp')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_request_otp_for_unknown_email_returns_404(): void
    {
        $this->postJson('/api/v1/auth/request-otp', ['email' => 'unknown@example.com'])
            ->assertStatus(404);
    }

    public function test_request_otp_for_known_user(): void
    {
        [, $admin] = $this->createTenantWithAdmin();

        $this->postJson('/api/v1/auth/request-otp', ['email' => $admin->email])
            ->assertStatus(200)
            ->assertJsonPath('message', fn($msg) => str_starts_with($msg, 'Code envoyé'));
    }

    public function test_verify_otp_returns_token(): void
    {
        [, $admin] = $this->createTenantWithAdmin();

        $this->postJson('/api/v1/auth/request-otp', ['email' => $admin->email]);

        $otp = OtpToken::where('email', $admin->email)->latest()->first();

        $this->postJson('/api/v1/auth/verify-otp', [
            'email' => $admin->email,
            'code'  => $otp->token,
        ])
            ->assertStatus(200)
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);
    }

    public function test_verify_otp_with_wrong_code_returns_422(): void
    {
        [, $admin] = $this->createTenantWithAdmin();

        $this->postJson('/api/v1/auth/request-otp', ['email' => $admin->email]);

        $this->postJson('/api/v1/auth/verify-otp', [
            'email' => $admin->email,
            'code'  => '000000',
        ])->assertStatus(422);
    }

    public function test_me_returns_authenticated_user(): void
    {
        [, $admin] = $this->createTenantWithAdmin();

        $this->actingAs($admin)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonFragment(['email' => $admin->email]);
    }

    public function test_suspended_tenant_is_blocked(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'suspended']);
        $admin  = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'company_admin']);

        $this->actingAs($admin)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(403)
            ->assertJsonFragment(['message' => 'Votre compte est suspendu. Contactez support@operix-app.com']);
    }

    public function test_trial_tenant_is_allowed(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'trial']);
        $admin  = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'company_admin']);

        $this->actingAs($admin)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200);
    }

    public function test_expired_trial_is_blocked(): void
    {
        $tenant = Tenant::factory()->create([
            'status'          => 'trial',
            'demo_expires_at' => now()->subDay(),
        ]);
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'company_admin']);

        $this->actingAs($admin)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(403);
    }

    public function test_business_user_without_tenant_is_blocked(): void
    {
        $admin = User::factory()->create(['tenant_id' => null, 'role' => 'company_admin']);

        $this->actingAs($admin)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(403);
    }
}
