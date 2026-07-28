<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Création de commande (checkout public).
 *
 * SÉCURITÉ : le client ne fournit QUE le choix commercial (plan + cycle + infos entreprise).
 * Le montant et la devise sont TOUJOURS recalculés serveur depuis le plan ; un amount/currency/
 * tenant_id fourni par le client est ignoré. L'initiation du paiement (redirection provider)
 * est branchée dans un commit ultérieur.
 */
class CheckoutController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_slug'     => ['required', 'string', 'exists:plans,slug'],
            'billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])],
            'company_name'  => ['required', 'string', 'max:255'],
            'contact_name'  => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:40'],
        ]);

        $plan = Plan::where('slug', $validated['plan_slug'])->firstOrFail();

        // Enterprise / plans sur devis / inactifs : pas de checkout automatique.
        abort_unless($plan->isPurchasable(), 422, 'Ce plan n’est pas disponible à l’achat en ligne. Contactez notre équipe commerciale.');

        // Montant calculé SERVEUR (centimes), jamais depuis le client.
        $amount = $plan->amountFor($validated['billing_cycle']);
        abort_if($amount === null, 422, 'Cycle de facturation indisponible pour ce plan.');

        $order = Order::create([
            'plan_id'       => $plan->id,
            'company_name'  => $validated['company_name'],
            'contact_name'  => $validated['contact_name'],
            'email'         => $validated['email'],
            'phone'         => $validated['phone'] ?? null,
            'billing_cycle' => $validated['billing_cycle'],
            'amount'        => $amount,
            'currency'      => $plan->currency,
            'status'        => 'pending',
        ]);

        app(\App\Services\CommercialNotifier::class)->orderConfirmation($order);

        return response()->json([
            'reference'     => $order->reference,
            'status'        => $order->status,
            'amount'        => $order->amount,     // centimes
            'currency'      => $order->currency,   // EUR
            'billing_cycle' => $order->billing_cycle,
            'plan'          => $plan->slug,
        ], 201);
    }
}
