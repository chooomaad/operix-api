@component('mail::message')
# Votre espace Operix est prêt

Bonjour,

L'espace HSSE de **{{ $companyName }}** vient d'être créé sur Operix.

@component('mail::button', ['url' => $activationUrl])
Activer mon compte
@endcomponent

Ce lien est à usage unique et expire prochainement. Vous définirez vous-même votre mot de passe.

Merci,<br>
L'équipe Operix
@endcomponent
