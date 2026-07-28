<?php

namespace App\Payments;

/**
 * Session de checkout renvoyée par un PaymentProvider : URL de redirection + référence provider.
 */
class CheckoutSession
{
    public function __construct(
        public readonly string $redirectUrl,
        public readonly string $providerReference,
    ) {
    }
}
