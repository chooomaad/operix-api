<?php

namespace App\Services;

use App\Models\PinResetToken;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Emission et verification des jetons de reinitialisation du PIN.
 *
 * Calque sur ActivationService (mecanisme deja eprouve du projet) :
 * - token cryptographiquement sur, renvoye UNE SEULE FOIS au caller (pour le
 *   lien de l'email) ;
 * - stocke uniquement sous forme hachee (sha256) ;
 * - usage unique (used_at) + expiration courte.
 *
 * Distinct d'ActivationService car les deux cycles de vie n'ont rien a voir :
 * l'activation cree un premier acces, le reset remplace un secret existant.
 */
class PinResetService
{
    /** Duree de validite d'un lien de reset, en minutes. */
    public const TTL_MINUTES = 30;

    /**
     * Emet un jeton pour l'utilisateur et renvoie le token EN CLAIR.
     *
     * Invalide d'abord les jetons non consommes de cet utilisateur : une
     * nouvelle demande doit rendre les liens precedents inoperants.
     */
    public function issue(User $user): string
    {
        PinResetToken::where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $plain = Str::random(64);

        PinResetToken::create([
            'user_id'    => $user->id,
            'token_hash' => $this->hash($plain),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        return $plain;
    }

    /** Resout un token en clair vers un jeton VALIDE, ou null. */
    public function resolve(string $plainToken): ?PinResetToken
    {
        $token = PinResetToken::where('token_hash', $this->hash($plainToken))->first();

        if (! $token || ! $token->isUsable()) {
            return null;
        }

        return $token;
    }

    /** Marque le jeton comme consomme (usage unique). */
    public function consume(PinResetToken $token): void
    {
        $token->update(['used_at' => now()]);
    }

    private function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
