<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marque la presence de l'utilisateur : met a jour `last_seen_at` a chaque requete
 * API authentifiee, mais AU PLUS une fois par minute (pour ne pas ecrire a chaque
 * appel). « En ligne » se derive ensuite d'un last_seen_at recent.
 *
 * updateQuietly : n'emet aucun evenement modele — donc ni audit, ni notification.
 */
class TrackPresence
{
    private const THROTTLE_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $stale = $user->last_seen_at === null
                || $user->last_seen_at->lt(now()->subSeconds(self::THROTTLE_SECONDS));

            if ($stale) {
                $user->last_seen_at = now();
                $user->saveQuietly();
            }
        }

        return $next($request);
    }
}
