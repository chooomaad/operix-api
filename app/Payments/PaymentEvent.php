<?php

namespace App\Payments;

/**
 * Événement de paiement normalisé (indépendant du prestataire).
 * Aucune donnée sensible (carte, CVV, secret, signature) ne transite ici.
 */
class PaymentEvent
{
    public function __construct(
        public readonly string $provider,
        public readonly string $transactionId,
        public readonly ?string $orderReference,
        public readonly int $amount,        // unités mineures (centimes)
        public readonly string $currency,
        public readonly string $status,     // succeeded | failed | pending
        public readonly array $sanitizedPayload = [],
    ) {
    }

    public function isSucceeded(): bool
    {
        return $this->status === 'succeeded';
    }
}
