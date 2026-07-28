<?php

namespace Tests\Feature\Api;

use App\Models\TenantActivation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ActivationTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithToken(): array
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $user   = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'company_admin', 'is_active' => false]);
        $token  = app(ActivationService::class)->issue($user, 60);

        return [$user, $token];
    }

    public function test_valid_token_activates_account_and_sets_password(): void
    {
        [$user, $token] = $this->adminWithToken();

        $this->postJson('/api/v1/activate', [
            'token'                 => $token,
            'password'              => 'MonMotDePasse1',
            'password_confirmation' => 'MonMotDePasse1',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('MonMotDePasse1', $user->password));

        // Token consommé → usage unique.
        $this->postJson('/api/v1/activate', [
            'token'                 => $token,
            'password'              => 'AutreMotDePasse2',
            'password_confirmation' => 'AutreMotDePasse2',
        ])->assertStatus(422);
    }

    public function test_expired_token_is_refused(): void
    {
        [, $token] = $this->adminWithToken();
        TenantActivation::latest('id')->first()->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/v1/activate', [
            'token'                 => $token,
            'password'              => 'MonMotDePasse1',
            'password_confirmation' => 'MonMotDePasse1',
        ])->assertStatus(422);
    }

    public function test_invalid_token_is_refused(): void
    {
        $this->postJson('/api/v1/activate', [
            'token'                 => 'jeton-bidon',
            'password'              => 'MonMotDePasse1',
            'password_confirmation' => 'MonMotDePasse1',
        ])->assertStatus(422);
    }
}
