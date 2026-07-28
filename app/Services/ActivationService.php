<?php

namespace App\Services;

use App\Models\TenantActivation;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Émission et vérification des jetons d'activation de compte.
 *
 * - Token cryptographiquement sûr, renvoyé UNE SEULE FOIS au caller (pour le lien email).
 * - Stocké uniquement sous forme hachée (sha256) → recherche déterministe, pas de clair en base.
 * - Usage unique (used_at) + expiration courte.
 */
class ActivationService
{
    /** Émet un jeton pour l'utilisateur et renvoie le token EN CLAIR (à mettre dans le lien). */
    public function issue(User $user, int $ttlMinutes = 1440): string
    {
        $plain = Str::random(64);

        TenantActivation::create([
            'tenant_id'  => $user->tenant_id,
            'user_id'    => $user->id,
            'token_hash' => $this->hash($plain),
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        return $plain;
    }

    /** Résout un token en clair vers une activation VALIDE (non utilisée, non expirée). */
    public function resolve(string $plainToken): ?TenantActivation
    {
        $activation = TenantActivation::where('token_hash', $this->hash($plainToken))->first();

        if (! $activation || ! $activation->isUsable()) {
            return null;
        }

        return $activation;
    }

    /** Marque le jeton comme consommé (usage unique). */
    public function consume(TenantActivation $activation): void
    {
        $activation->update(['used_at' => now()]);
    }

    private function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
