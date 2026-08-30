<x-mail::message>
# Réinitialisation de votre PIN

Bonjour {{ $name }},

Une réinitialisation du code PIN de votre compte **Operix TCN** a été demandée.

Cliquez sur le bouton ci-dessous pour définir un nouveau PIN.

<x-mail::button :url="$resetUrl">
Réinitialiser mon PIN
</x-mail::button>

Ce lien est valable **{{ $ttlMinutes }} minutes**. Passé ce délai, il faudra en
demander un nouveau.

**Vous n'êtes pas à l'origine de cette demande ?** Ignorez cet email : votre PIN
reste inchangé. En cas de doute, contactez votre administrateur.

Merci,
L'équipe Operix TCN
</x-mail::message>
