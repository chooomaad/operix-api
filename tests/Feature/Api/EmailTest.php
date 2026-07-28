<?php

namespace Tests\Feature\Api;

use App\Mail\ActivationMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\PaymentReceivedMail;
use App\Models\Order;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class EmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_queues_order_confirmation_email(): void
    {
        Mail::fake();
        Plan::factory()->create(['slug' => 'starter', 'price_monthly' => 4900, 'currency' => 'EUR']);

        $this->postJson('/api/v1/checkout', [
            'plan_slug'     => 'starter',
            'billing_cycle' => 'monthly',
            'company_name'  => 'Port Alpha',
            'contact_name'  => 'Awa',
            'email'         => 'awa@port-alpha.mr',
        ])->assertStatus(201);

        Mail::assertQueued(OrderConfirmationMail::class, fn ($m) => $m->hasTo('awa@port-alpha.mr'));
    }

    public function test_successful_webhook_queues_payment_and_activation_emails(): void
    {
        Mail::fake();
        config(['operix.payment.fake_secret' => 'secret123']);

        $plan  = Plan::factory()->create(['slug' => 'business']);
        $order = Order::factory()->create(['plan_id' => $plan->id, 'amount' => 4900, 'currency' => 'EUR', 'status' => 'pending', 'email' => 'buyer@corp.mr']);

        $payload = [
            'event' => 'payment.succeeded', 'transaction_id' => 'tx_' . Str::random(6),
            'order_reference' => $order->reference, 'amount' => 4900, 'currency' => 'EUR', 'status' => 'succeeded',
        ];
        $this->postSignedWebhook($payload)->assertStatus(200);

        Mail::assertQueued(PaymentReceivedMail::class);
        Mail::assertQueued(ActivationMail::class);
    }

    public function test_activation_mail_renders(): void
    {
        // Vérifie que la vue markdown compile réellement (pas seulement fake).
        $html = (new ActivationMail('Port Alpha', 'https://app.operix-app.com/activate?token=abc'))->render();
        $this->assertStringContainsString('Activer mon compte', $html);
        $this->assertStringContainsString('Port Alpha', $html);
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
