<?php

namespace App\Payments;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Provider FICTIF pour le développement et les tests (aucune passerelle réelle).
 *
 * - createCheckout : renvoie une URL de « page de paiement » simulée.
 * - verifyWebhook  : valide une signature HMAC-SHA256 du corps brut (X-Operix-Signature).
 * - parseWebhook   : normalise + ASSAINIT (allowlist) le payload.
 *
 * Permet de dérouler et tester tout le workflow paiement→provisioning sans prestataire réel.
 */
class FakePaymentProvider implements PaymentProvider
{
    public function name(): string
    {
        return 'fake';
    }

    public function createCheckout(Order $order): CheckoutSession
    {
        $providerRef = 'fake_' . Str::uuid();
        $return = rtrim(config('operix.payment.checkout_return_url', ''), '/');

        return new CheckoutSession(
            redirectUrl: "{$return}/pay?order={$order->reference}&session={$providerRef}",
            providerReference: $providerRef,
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = (string) config('operix.payment.fake_secret', '');
        $signature = (string) $request->header('X-Operix-Signature', '');

        if ($secret === '' || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhook(Request $request): PaymentEvent
    {
        $data = $request->json()->all();

        // ALLOWLIST : on ne conserve QUE l'utile (traçabilité/réconciliation/audit).
        $sanitized = [
            'event'          => $data['event'] ?? null,
            'transaction_id' => $data['transaction_id'] ?? null,
            'order_reference'=> $data['order_reference'] ?? null,
            'status'         => $data['status'] ?? null,
        ];

        return new PaymentEvent(
            provider: $this->name(),
            transactionId: (string) ($data['transaction_id'] ?? ''),
            orderReference: $data['order_reference'] ?? null,
            amount: (int) ($data['amount'] ?? 0),
            currency: (string) ($data['currency'] ?? 'EUR'),
            status: (string) ($data['status'] ?? 'pending'),
            sanitizedPayload: $sanitized,
        );
    }

    public function retrievePayment(string $transactionId): ?PaymentEvent
    {
        // Provider fictif : pas de vérification serveur distante.
        return null;
    }
}
