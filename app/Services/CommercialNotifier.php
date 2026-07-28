<?php

namespace App\Services;

use App\Mail\ActivationMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\PaymentReceivedMail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Point d'entrée unique des emails transactionnels commerciaux.
 * Les Mailables implémentent ShouldQueue → envoi en file d'attente (contrôleurs allégés).
 */
class CommercialNotifier
{
    public function activation(User $admin, string $token): void
    {
        $url     = rtrim(config('operix.app_url'), '/') . '/activate?token=' . $token;
        $company = $admin->tenant?->name ?? '';

        Mail::to($admin->email)->send(new ActivationMail($company, $url));
    }

    public function orderConfirmation(Order $order): void
    {
        Mail::to($order->email)->send(new OrderConfirmationMail($order));
    }

    public function paymentReceived(Order $order): void
    {
        Mail::to($order->email)->send(new PaymentReceivedMail($order));
    }
}
