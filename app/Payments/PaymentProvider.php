<?php

namespace App\Payments;

use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Contrat d'un prestataire de paiement. Aucune logique métier Operix ne doit dépendre
 * directement d'un provider concret (Stripe, Bankily, Masrivi…). Le provider réel sera
 * choisi et branché séparément, sur validation.
 */
interface PaymentProvider
{
    public function name(): string;

    /** Crée une session de paiement et renvoie l'URL de redirection. */
    public function createCheckout(Order $order): CheckoutSession;

    /** Vérifie l'authenticité d'un webhook (signature / HMAC). SEULE preuve de paiement. */
    public function verifyWebhook(Request $request): bool;

    /** Normalise le webhook en PaymentEvent (payload assaini). */
    public function parseWebhook(Request $request): PaymentEvent;

    /** Vérification serveur d'une transaction (défense complémentaire / réconciliation). */
    public function retrievePayment(string $transactionId): ?PaymentEvent;
}
