<?php

use Illuminate\Support\Facades\Broadcast;

// Canal privé de notifications par utilisateur
// Le frontend s'abonne à "user.{id}" pour recevoir ses notifications en temps réel
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal de présence du tenant (optionnel — pour le nombre d'utilisateurs connectés)
Broadcast::channel('tenant.{tenantId}', function ($user, $tenantId) {
    if ((int) $user->tenant_id === (int) $tenantId) {
        return ['id' => $user->id, 'name' => $user->name, 'role' => $user->role];
    }
    return false;
});
