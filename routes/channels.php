<?php

use App\Models\User;
use App\Support\RealtimeChannelAccess;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Autorisation des canaux temps reel
|--------------------------------------------------------------------------
|
| Le cloisonnement du temps reel ne repose PAS sur le contenu des messages mais
| sur QUI a le droit d'ecouter. C'est la seule garantie qui tienne : un message
| deja parti ne se rappelle pas. Chaque regle ci-dessous est donc la vraie
| frontiere de securite, evaluee par le serveur WebSocket a chaque abonnement.
|
| Une simple egalite d'identifiant ne suffit pas : un compte peut avoir ete
| desactive et un tenant suspendu APRES l'ouverture de la session, alors que le
| jeton Sanctum reste valide. RealtimeChannelAccess revalide donc l'etat a chaque
| autorisation (cf. sa documentation).
*/

// Canal prive de notifications par utilisateur.
// Le frontend s'abonne a "user.{id}" pour ses notifications personnelles.
Broadcast::channel('user.{id}', function (User $user, $id) {
    // Changer l'identifiant dans l'URL (« user.999 ») ne doit jamais donner acces
    // au canal d'un autre : egalite stricte ET compte encore habilite.
    return (int) $user->id === (int) $id && RealtimeChannelAccess::usable($user);
});

// Canal prive des evenements HSE de l'entreprise.
// Presence : porte aussi le nombre d'utilisateurs connectes de l'entreprise.
Broadcast::channel('tenant.{tenantId}', function (User $user, $tenantId) {
    if ((int) $user->tenant_id !== (int) $tenantId || ! RealtimeChannelAccess::usable($user)) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->name, 'role' => $user->role];
});
