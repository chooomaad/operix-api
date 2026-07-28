<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\PaymentProvider;
use App\Services\ProvisioningService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Réception des webhooks de paiement — SEULE preuve de paiement.
 *
 * Une redirection frontend /payment-success ne prouve JAMAIS un paiement. Le provisioning
 * ne démarre qu'après : signature vérifiée → order trouvée → idempotence → montant & devise
 * vérifiés côté serveur → paiement succeeded. Un webhook reçu N fois ne crée jamais plusieurs
 * entreprises (idempotence à plusieurs niveaux : transaction unique, order déjà payée, job idempotent).
 */
class PaymentWebhookController extends Controller
{
    public function handle(Request $request, string $provider, PaymentProvider $payment, ProvisioningService $provisioning): JsonResponse
    {
        // 1. Signature (authenticité) — sinon rejet.
        if (! $payment->verifyWebhook($request)) {
            return response()->json(['message' => 'Signature invalide.'], 400);
        }

        $event = $payment->parseWebhook($request);

        // 2. Order cible.
        $order = Order::where('reference', $event->orderReference)->first();
        if (! $order) {
            return response()->json(['message' => 'Commande introuvable.'], 404);
        }

        // 3. Idempotence : transaction déjà enregistrée → no-op (le provider peut réessayer).
        $alreadyProcessed = Payment::where('provider', $event->provider)
            ->where('provider_transaction_id', $event->transactionId)
            ->exists();
        if ($alreadyProcessed) {
            return response()->json(['message' => 'Événement déjà traité.'], 200);
        }

        // 4. Order déjà payée → ne jamais reprovisionner.
        if ($order->isPaid()) {
            return response()->json(['message' => 'Commande déjà payée.'], 200);
        }

        // 5. Montant & devise vérifiés CÔTÉ SERVEUR (jamais depuis le client).
        if ($event->amount !== $order->amount || strtoupper($event->currency) !== strtoupper($order->currency)) {
            Log::warning('Webhook paiement rejeté : montant/devise incohérents', [
                'order' => $order->reference, 'expected' => [$order->amount, $order->currency],
                'received' => [$event->amount, $event->currency],
            ]);
            return response()->json(['message' => 'Montant ou devise incohérent.'], 422);
        }

        // 6. Paiement non abouti → enregistrer l'échec, pas de provisioning.
        if (! $event->isSucceeded()) {
            $this->recordPayment($order, $event, 'failed');
            $order->update(['status' => 'failed']);
            return response()->json(['message' => 'Paiement non abouti.'], 200);
        }

        // 7. Succès — enregistrement + passage à paid dans une transaction (unique = anti-replay).
        try {
            DB::transaction(function () use ($order, $event) {
                $this->recordPayment($order, $event, 'succeeded');
                $order->update(['status' => 'paid', 'paid_at' => now()]);
            });
        } catch (QueryException $e) {
            // Course : un webhook concurrent a inséré la même transaction → idempotent.
            return response()->json(['message' => 'Événement déjà traité.'], 200);
        }

        // 8. Provisioning transactionnel et idempotent.
        $result = $provisioning->provisionFromOrder($order->fresh());

        // 9. Emails transactionnels (en queue) — uniquement au 1er provisioning.
        if ($result->created) {
            $notifier = app(\App\Services\CommercialNotifier::class);
            $notifier->paymentReceived($order);
            if ($result->activationToken) {
                $notifier->activation($result->admin, $result->activationToken);
            }
        }

        return response()->json(['message' => 'ok', 'tenant' => $result->tenant->slug], 200);
    }

    private function recordPayment(Order $order, \App\Payments\PaymentEvent $event, string $status): void
    {
        Payment::create([
            'order_id'                => $order->id,
            'provider'                => $event->provider,
            'provider_transaction_id' => $event->transactionId,
            'amount'                  => $event->amount,
            'currency'                => strtoupper($event->currency),
            'status'                  => $status,
            'paid_at'                 => $status === 'succeeded' ? now() : null,
            'sanitized_payload'       => $event->sanitizedPayload,
        ]);
    }
}
