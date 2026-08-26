<?php

namespace Tests\Feature\Api;

use App\Models\OtpToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    /**
     * Non-regression B1 : chaque connexion executait `$user->tokens()->delete()`,
     * si bien qu'ouvrir la session mobile fermait la session web du meme utilisateur.
     * Les deux plateformes doivent pouvoir coexister.
     */
    public function test_mobile_login_does_not_revoke_the_web_session(): void
    {
        [, $admin] = $this->createTenantWithAdmin();
        $admin->update(['matricule' => 'TCN-SESSION-1', 'password' => Hash::make('1234')]);

        $web = $this->postJson('/api/v1/auth/login', [
            'matricule' => 'TCN-SESSION-1',
            'pin'       => '1234',
            'platform'  => 'web',
        ])->assertStatus(200)->json('token');

        $mobile = $this->postJson('/api/v1/auth/login', [
            'matricule' => 'TCN-SESSION-1',
            'pin'       => '1234',
            'platform'  => 'mobile',
        ])->assertStatus(200)->json('token');

        $this->assertNotSame($web, $mobile);

        // Le jeton web doit rester utilisable apres la connexion mobile.
        $this->withHeader('Authorization', "Bearer {$web}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200);

        $this->withHeader('Authorization', "Bearer {$mobile}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200);

        $this->assertSame(
            ['operix-mobile', 'operix-web'],
            $admin->tokens()->pluck('name')->sort()->values()->all()
        );
    }

    /**
     * Une nouvelle connexion sur LA MEME plateforme doit invalider la precedente :
     * un telephone perdu ne conserve pas d'acces.
     */
    public function test_second_login_on_same_platform_revokes_the_previous_one(): void
    {
        [, $admin] = $this->createTenantWithAdmin();
        $admin->update(['matricule' => 'TCN-SESSION-1', 'password' => Hash::make('1234')]);

        $first = $this->postJson('/api/v1/auth/login', [
            'matricule' => 'TCN-SESSION-1',
            'pin'       => '1234',
            'platform'  => 'mobile',
        ])->assertStatus(200)->json('token');

        $this->postJson('/api/v1/auth/login', [
            'matricule' => 'TCN-SESSION-1',
            'pin'       => '1234',
            'platform'  => 'mobile',
        ])->assertStatus(200);

        $this->withHeader('Authorization', "Bearer {$first}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    /**
     * Le nom du jeton vient du client : toute valeur hors liste blanche doit retomber
     * sur 'web' plutot que de creer un nom de jeton arbitraire.
     */
    public function test_unknown_platform_falls_back_to_web(): void
    {
        [, $admin] = $this->createTenantWithAdmin();
        $admin->update(['matricule' => 'TCN-SESSION-1', 'password' => Hash::make('1234')]);

        $this->postJson('/api/v1/auth/login', [
            'matricule' => 'TCN-SESSION-1',
            'pin'       => '1234',
            'platform'  => 'operix-mobile-forge',
        ])->assertStatus(200);

        $this->assertSame(['operix-web'], $admin->tokens()->pluck('name')->all());
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

    public function test_registration_assigns_the_public_tcn_tenant(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'tcn']);

        $this->postJson('/api/v1/auth/register', [
            'prenom'           => 'Choumad',
            'nom'              => 'Elemine',
            'matricule'        => 'TCN-REQ-001',
            'email'            => 'choumad.registration@example.com',
            'pin'              => '123456',
            'pin_confirmation' => '123456',
        ])
            ->assertStatus(201)
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'Demande'));

        $this->assertDatabaseHas('users', [
            'email'    => 'choumad.registration@example.com',
            'tenant_id' => $tenant->id,
            'role'     => 'agent',
            'is_active' => false,
        ]);
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
