@component('mail::message')
# Paiement confirmé

Bonjour,

Votre paiement pour la commande **{{ $order->reference }}** a bien été reçu.
Votre espace Operix est en cours d'activation — vous allez recevoir votre lien d'activation.

Merci,<br>
L'équipe Operix
@endcomponent
