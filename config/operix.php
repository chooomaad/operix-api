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

];
