<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Payments\PaymentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PaymentProviderTest extends TestCase
{
    use RefreshDatabase;

    private function jsonRequest(string $body, ?string $signature): Request
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($signature !== null) {
            $server['HTTP_X_OPERIX_SIGNATURE'] = $signature;
        }

        return Request::create('/webhook', 'POST', [], [], [], $server, $body);
    }

    public function test_fake_provider_creates_checkout_verifies_signature_and_sanitizes_payload(): void
    {
        config(['operix.payment.fake_secret' => 'secret123']);

        $provider = app(PaymentProvider::class);
        $this->assertSame('fake', $provider->name());

        $order = Order::factory()->create();

        // createCheckout
        $session = $provider->createCheckout($order);
        $this->assertStringContainsString($order->reference, $session->redirectUrl);
        $this->assertStringStartsWith('fake_', $session->providerReference);

        // Payload contenant volontairement une donnée sensible (ne doit jamais être conservée).
        $body = json_encode([
            'transaction_id'  => 'tx_1',
            'order_reference' => $order->reference,
            'amount'          => 4900,
            'currency'        => 'EUR',
            'status'          => 'succeeded',
            'card_number'     => '4242424242424242',
            'cvv'             => '123',
        ]);
        $signature = hash_hmac('sha256', $body, 'secret123');

        // Signature valide → true
        $this->assertTrue($provider->verifyWebhook($this->jsonRequest($body, $signature)));
        // Signature invalide → false
        $this->assertFalse($provider->verifyWebhook($this->jsonRequest($body, 'wrong-signature')));
        // Signature absente → false
        $this->assertFalse($provider->verifyWebhook($this->jsonRequest($body, null)));

        // parseWebhook normalise + assainit (allowlist)
        $event = $provider->parseWebhook($this->jsonRequest($body, $signature));
        $this->assertSame('fake', $event->provider);
        $this->assertSame('tx_1', $event->transactionId);
        $this->assertSame(4900, $event->amount);
        $this->assertSame('EUR', $event->currency);
        $this->assertTrue($event->isSucceeded());

        // AUCUNE donnée sensible dans le payload assaini.
        $this->assertArrayNotHasKey('card_number', $event->sanitizedPayload);
        $this->assertArrayNotHasKey('cvv', $event->sanitizedPayload);
    }
}
