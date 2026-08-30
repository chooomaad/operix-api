<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-tetes de securite HTTP, ajoutes a chaque reponse de l'API.
 *
 * Choix mesures : ces en-tetes durcissent le navigateur sans casser une API JSON
 * consommee par un SPA. On evite volontairement une Content-Security-Policy ici —
 * elle se definit au niveau du serveur qui sert le SPA (Vue/Vite), pas de l'API,
 * et une CSP mal cadree casserait l'application.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Empeche le navigateur de "deviner" un type MIME (protege d'un contenu
        // servi comme un type inattendu).
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Interdit l'inclusion de l'API dans une iframe (anti clickjacking). L'API
        // n'a aucune raison d'etre encadree.
        $response->headers->set('X-Frame-Options', 'DENY');

        // Ne fuit pas l'URL complete (qui peut porter des identifiants) vers une
        // origine tierce.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Coupe l'acces aux capteurs sensibles par defaut pour toute page servie
        // par ce backend.
        $response->headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');

        // HSTS UNIQUEMENT en production et sur HTTPS : force le navigateur a
        // rester en HTTPS. L'imposer en developpement (http local) rendrait le
        // site inaccessible apres une premiere visite.
        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
