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
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);
        $middleware->alias([
            'tenant.scope' => \App\Http\Middleware\TenantScope::class,   // vestige (no-op) conservé
            'tenant'       => \App\Http\Middleware\ResolveTenant::class,
            'tenant.context' => \App\Http\Middleware\EnsureTenantContext::class,
            'permission'   => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'superadmin'   => \App\Http\Middleware\SuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
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
