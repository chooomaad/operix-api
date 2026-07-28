<?php

namespace Tests\Feature\Api;

use App\Models\TenantActivation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_is_hashed_single_use_and_expiring(): void
    {
        $tenant = Tenant::factory()->create();
        $user   = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'company_admin']);
        $svc    = app(ActivationService::class);

        $plain = $svc->issue($user, 60);

        // Le token EN CLAIR n'est jamais stocké ; seul son hash l'est.
        $this->assertDatabaseMissing('tenant_activations', ['token_hash' => $plain]);
        $this->assertDatabaseHas('tenant_activations', [
            'token_hash' => hash('sha256', $plain),
            'user_id'    => $user->id,
        ]);

        // Token valide → activation.
        $activation = $svc->resolve($plain);
        $this->assertNotNull($activation);

        // Mauvais token → null.
        $this->assertNull($svc->resolve('mauvais-token'));

        // Usage unique : après consommation → null.
        $svc->consume($activation);
        $this->assertNull($svc->resolve($plain));

        // Expiration : token expiré → null.
        $plain2 = $svc->issue($user, 60);
        TenantActivation::latest('id')->first()->update(['expires_at' => now()->subMinute()]);
        $this->assertNull($svc->resolve($plain2));
    }
}
