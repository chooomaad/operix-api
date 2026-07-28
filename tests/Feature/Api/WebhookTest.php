<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['operix.payment.fake_secret' => 'secret123']);
    }

    private function order(array $override = []): Order
    {
        $plan = Plan::factory()->create(['slug' => 'business']);
        return Order::factory()->create(array_merge([
            'plan_id'  => $plan->id,
            'amount'   => 4900,
            'currency' => 'EUR',
            'status'   => 'pending',
        ], $override));
    }

    private function payload(Order $order, array $override = []): array
    {
        return array_merge([
            'event'           => 'payment.succeeded',
            'transaction_id'  => 'tx_' . Str::random(10),
            'order_reference' => $order->reference,
            'amount'          => $order->amount,
            'currency'        => $order->currency,
            'status'          => 'succeeded',
        ], $override);
    }

    private function postWebhook(array $payload, string $signingSecret = 'secret123'): TestResponse
    {
        $content = json_encode($payload);
        $sig = hash_hmac('sha256', $content, $signingSecret);

        return $this->call('POST', '/api/v1/webhooks/payments/fake', [], [], [], [
            'HTTP_X_OPERIX_SIGNATURE' => $sig,
            'CONTENT_TYPE'            => 'application/json',
            'HTTP_ACCEPT'             => 'application/json',
        ], $content);
    }

    public function test_valid_webhook_marks_order_paid_and_provisions_tenant(): void
    {
        $order = $this->order();

        $this->postWebhook($this->payload($order))->assertStatus(200);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
        $this->assertSame(1, Tenant::count());
        $this->assertDatabaseHas('users', ['email' => $order->email, 'role' => 'company_admin']);
        $this->assertDatabaseHas('subscriptions', ['order_id' => $order->id, 'status' => 'active']);
    }

    public function test_forged_signature_is_rejected_and_nothing_provisioned(): void
    {
        $order = $this->order();

        $this->postWebhook($this->payload($order), 'wrong-secret')->assertStatus(400);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertSame(0, Tenant::count());
    }

    public function test_duplicate_webhook_never_provisions_twice(): void
    {
        $order = $this->order();
        $payload = $this->payload($order, ['transaction_id' => 'tx_fixed']);

        $this->postWebhook($payload)->assertStatus(200);
        $this->postWebhook($payload)->assertStatus(200);   // rejoué
        $this->postWebhook($payload)->assertStatus(200);   // rejoué

        $this->assertSame(1, Tenant::count());
        $this->assertSame(1, \App\Models\Payment::count());
    }

    public function test_wrong_amount_is_rejected_without_provisioning(): void
    {
        $order = $this->order(['amount' => 4900]);

        $this->postWebhook($this->payload($order, ['amount' => 1]))->assertStatus(422);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertSame(0, Tenant::count());
    }

    public function test_wrong_currency_is_rejected_without_provisioning(): void
    {
        $order = $this->order(['currency' => 'EUR']);

        $this->postWebhook($this->payload($order, ['currency' => 'USD']))->assertStatus(422);
        $this->assertSame(0, Tenant::count());
    }

    public function test_unknown_order_returns_404(): void
    {
        $order = $this->order();
        $this->postWebhook($this->payload($order, ['order_reference' => 'OPX-2026-999999']))
            ->assertStatus(404);
        $this->assertSame(0, Tenant::count());
    }

    public function test_already_paid_order_is_not_reprovisioned(): void
    {
        $order = $this->order(['status' => 'paid', 'paid_at' => now()]);

        $this->postWebhook($this->payload($order))->assertStatus(200);
        $this->assertSame(0, Tenant::count());
    }
}
