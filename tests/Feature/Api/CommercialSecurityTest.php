<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ActivationService;
use App\Services\ProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CommercialSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_provisioning_rolls_back_entirely_on_failure(): void
    {
        $plan  = Plan::factory()->create();
        $order = Order::factory()->create(['plan_id' => $plan->id, 'status' => 'paid']);

        // ActivationService défaillant → l'émission du token lève une exception APRÈS la
        // création de tenant/admin/subscription. La transaction doit tout annuler.
        $this->app->bind(ActivationService::class, fn () => new class extends ActivationService {
            public function issue(User $user, int $ttlMinutes = 1440): string
            {
                throw new \RuntimeException('échec simulé');
            }
        });

        try {
            app(ProvisioningService::class)->provisionFromOrder($order);
            $this->fail('Une exception était attendue.');
        } catch (\Throwable $e) {
            // attendu
        }

        // Aucun environnement partiel : rollback complet.
        $this->assertSame(0, Tenant::count());
        $this->assertSame(0, \App\Models\Subscription::count());
        $this->assertNull($order->fresh()->tenant_id);
    }

    public function test_failed_payment_webhook_marks_order_failed_without_provisioning(): void
    {
        config(['operix.payment.fake_secret' => 'secret123']);
        $plan  = Plan::factory()->create();
        $order = Order::factory()->create(['plan_id' => $plan->id, 'amount' => 4900, 'currency' => 'EUR', 'status' => 'pending']);

        $payload = [
            'event' => 'payment.failed', 'transaction_id' => 'tx_' . Str::random(6),
            'order_reference' => $order->reference, 'amount' => 4900, 'currency' => 'EUR', 'status' => 'failed',
        ];

        $this->postSignedWebhook($payload)->assertStatus(200);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'failed']);
        $this->assertDatabaseHas('payments', ['provider_transaction_id' => $payload['transaction_id'], 'status' => 'failed']);
        $this->assertSame(0, Tenant::count());
    }

    public function test_agent_is_forbidden_from_superadmin(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $agent  = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'agent']);

        $this->actingAs($agent)->getJson('/api/v1/superadmin/dashboard')->assertStatus(403);
        $this->actingAs($agent)->getJson('/api/v1/superadmin/orders')->assertStatus(403);
    }

    private function postSignedWebhook(array $payload): TestResponse
    {
        $content = json_encode($payload);
        $sig = hash_hmac('sha256', $content, 'secret123');

        return $this->call('POST', '/api/v1/webhooks/payments/fake', [], [], [], [
            'HTTP_X_OPERIX_SIGNATURE' => $sig,
            'CONTENT_TYPE'            => 'application/json',
            'HTTP_ACCEPT'             => 'application/json',
        ], $content);
    }
}
