@component('mail::message')
# Commande enregistrée

Bonjour,

Nous avons bien enregistré votre commande **{{ $order->reference }}**
({{ number_format($order->amount / 100, 2) }} {{ $order->currency }} / {{ $order->billing_cycle }}).

Vous recevrez une confirmation dès la validation de votre paiement.

Merci,<br>
L'équipe Operix
@endcomponent
