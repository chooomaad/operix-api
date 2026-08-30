<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    | Supported: "reverb", "pusher", "ably", "redis", "log", "null"
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over WebSockets. Samples of
    | each available type of connection are provided inside this array.
    |
    */

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
                'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'host' => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Ably via le protocole Pusher
        |----------------------------------------------------------------------
        |
        | Ably est le transport WebSocket manage retenu pour Operix TCN en
        | production. On l'atteint par son adaptateur PROTOCOLE PUSHER, et non par
        | le pilote natif `ably` : le projet embarque deja `pusher/pusher-php-server`
        | (serveur) et `pusher-js` (navigateur). Reutiliser ces dependances evite
        | d'ajouter `ably/ably-php` + un client Ably cote Vue — deux librairies de
        | plus qui feraient exactement le meme travail.
        |
        | Mapping des cles Ably -> Pusher :
        |   ABLY_APP_ID     = identifiant d'application Ably (avant le « . » de la cle)
        |   ABLY_PUBLIC_KEY = « appId.keyId » — partie PUBLIQUE, exposable au client
        |   ABLY_SECRET     = secret de la cle (apres le « : ») — SERVEUR UNIQUEMENT
        |
        | Le secret ne quitte jamais le serveur : la signature de /broadcasting/auth
        | est calculee ici. Le client ne recoit que ABLY_PUBLIC_KEY (voir le Vite du
        | frontend, VITE_ABLY_PUBLIC_KEY).
        */
        'ably' => [
            'driver' => 'pusher',
            'key' => env('ABLY_PUBLIC_KEY'),
            'secret' => env('ABLY_SECRET'),
            'app_id' => env('ABLY_APP_ID'),
            'options' => [
                'host' => env('ABLY_HOST', 'rest-pusher.ably.io'),
                'port' => 443,
                'scheme' => 'https',
                'encrypted' => true,
                'useTLS' => true,
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
