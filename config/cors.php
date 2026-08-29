<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Allowed origins for the Operix API.
    | In production, restrict to the exact domain(s) of the frontend.
    |
    */

    'paths' => [
        'api/*',
        // Signature des abonnements aux canaux prives. Ce point vit a la RACINE
        // du serveur, hors de `api/*` : il n'etait donc couvert par aucune regle
        // CORS, et le navigateur bloquait la requete.
        //
        // Le symptome etait trompeur : le WebSocket echappe au CORS, donc la
        // connexion s'etablissait et l'interface affichait « En direct ». Seul
        // l'abonnement echouait, et AUCUN evenement n'arrivait jamais.
        //
        // Le client web du meme depot n'etait pas touche : son proxy Vite le sert
        // en meme origine. Tout autre client navigateur l'etait.
        'broadcasting/auth',
        'sanctum/csrf-cookie',
        'docs/api',
        'docs/api.json',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '*')))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'Accept',
        'X-XSRF-TOKEN',
        'X-Tenant-Slug',
        'Cache-Control',
    ],

    'exposed_headers' => [
        'X-Request-Id',
    ],

    'max_age' => 86400, // 24h preflight cache

    'supports_credentials' => true,

];
