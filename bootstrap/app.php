<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Les canaux sont declares ici plutot que via `withRouting(channels:)` afin de
    // choisir le middleware du point d'authentification.
    //
    // Par defaut, /broadcasting/auth n'accepte que le middleware `web`, donc une
    // session par cookie. Or les deux clients Operix s'authentifient par jeton
    // Bearer : le SPA web (withCredentials: false) comme le mobile. Sans
    // `auth:sanctum`, aucun d'eux ne peut s'abonner a un canal prive — l'abonnement
    // echoue en 403 et le temps reel ne fonctionne tout simplement pas.
    //
    // Le garde sanctum couvre les deux cas : il valide un jeton Bearer et retombe
    // sur la session pour un SPA en cookies, si ce mode etait adopte plus tard.
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Derrière le load balancer TLS de Render : faire confiance aux en-têtes
        // X-Forwarded-* pour que Laravel détecte le HTTPS (URL signées cohérentes,
        // fin du « mixed content » sur les images/logos servis en http).
        $middleware->trustProxies(at: '*');

        $middleware->api(prepend: [
            \App\Http\Middleware\QueryCountHeaders::class,
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);
        // En-tetes de securite sur toute reponse API.
        $middleware->api(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
        $middleware->alias([
            'tenant.scope' => \App\Http\Middleware\TenantScope::class,   // vestige (no-op) conservé
            'tenant'       => \App\Http\Middleware\ResolveTenant::class,
            'tenant.context' => \App\Http\Middleware\EnsureTenantContext::class,
            'presence' => \App\Http\Middleware\TrackPresence::class,
            'permission'   => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'superadmin'   => \App\Http\Middleware\SuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // `broadcasting/*` est inclus au meme titre que l'API : ce point d'entree
        // n'est appele que par du JavaScript. Sans cela, une requete non
        // authentifiee y declenche une redirection vers une route `login`
        // inexistante, donc une 500 accompagnee d'une page HTML — un client au
        // jeton expire ne pouvait pas distinguer « session terminee » de
        // « serveur en panne ».
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('broadcasting/*'),
        );

        // Spatie renvoie un message anglais et expose la permission manquante.
        // On uniformise sur le message des autres refus de l'API et on ne divulgue
        // pas le nom de la permission attendue.
        $exceptions->render(function (
            \Spatie\Permission\Exceptions\UnauthorizedException $e,
            Request $request
        ) {
            if ($request->is('api/*')) {
                return response()->json(
                    ['message' => 'Accès refusé. Permission insuffisante.'],
                    403
                );
            }

            return null;
        });
    })->create();
