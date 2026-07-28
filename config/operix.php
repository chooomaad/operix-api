<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disque de stockage des médias tenant
    |--------------------------------------------------------------------------
    |
    | Disque privé (jamais servi publiquement) utilisé pour TOUS les fichiers
    | métier appartenant à une entreprise. Configuré dans config/filesystems.php
    | (local en dev, MinIO/S3 en prod, Cloudflare R2 compatible S3 sans changement
    | de code — il suffira de pointer MEDIA_DISK_DRIVER=s3 + credentials R2).
    |
    */
    'media_disk' => env('MEDIA_DISK', 'tenant-media'),

    /*
    |--------------------------------------------------------------------------
    | Durée de validité des URLs signées de fichiers (minutes)
    |--------------------------------------------------------------------------
    */
    'signed_url_ttl' => (int) env('MEDIA_SIGNED_URL_TTL', 30),

    /*
    |--------------------------------------------------------------------------
    | Commercial (SaaS)
    |--------------------------------------------------------------------------
    |
    | EUR = devise commerciale officielle (source de vérité, montants en centimes).
    | MRU = devise d'affichage indicative (équivalence calculée via exchange_rates,
    | jamais hardcodée côté client). default_display_rate = fallback si aucune ligne
    | en base (valeur PLACEHOLDER — à mettre à jour avec le taux réel).
    |
    */
    'commercial_currency' => env('OPERIX_COMMERCIAL_CURRENCY', 'EUR'),
    'display_currency'    => env('OPERIX_DISPLAY_CURRENCY', 'MRU'),
    'default_display_rate' => (float) env('OPERIX_DEFAULT_DISPLAY_RATE', 43.0),

    /*
    |--------------------------------------------------------------------------
    | Paiement
    |--------------------------------------------------------------------------
    |
    | provider : implémentation active de App\Payments\PaymentProvider.
    | AUCUN prestataire réel n'est connecté (uniquement 'fake' pour dev/tests).
    | Le provider réel (marché Mauritanie / international) sera choisi séparément.
    |
    */
    'payment' => [
        'provider'            => env('OPERIX_PAYMENT_PROVIDER', 'fake'),
        'fake_secret'         => env('OPERIX_FAKE_PAYMENT_SECRET', 'fake-webhook-secret'),
        'checkout_return_url' => env('OPERIX_CHECKOUT_RETURN_URL', 'https://app.operix-app.com/checkout'),
    ],

];

