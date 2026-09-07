<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        // ── Médias Operix — local en dev, S3-compatible en prod ───────────────
        // Compatible Cloudflare R2 / MinIO / AWS S3. Variables MEDIA_S3_* (claires,
        // agnostiques du fournisseur) en priorité, avec repli sur MINIO_* / AWS_*.
        // Les fichiers restent PRIVÉS : ils sont servis par l'application (lien
        // signé), jamais par une URL S3 publique.
        'tenant-media' => match (env('MEDIA_DISK_DRIVER', 'local')) {
            's3' => [
                'driver'                  => 's3',
                'key'                     => env('MEDIA_S3_KEY', env('MINIO_KEY', env('AWS_ACCESS_KEY_ID'))),
                'secret'                  => env('MEDIA_S3_SECRET', env('MINIO_SECRET', env('AWS_SECRET_ACCESS_KEY'))),
                'region'                  => env('MEDIA_S3_REGION', env('MINIO_REGION', 'auto')),
                'bucket'                  => env('MEDIA_S3_BUCKET', env('MINIO_BUCKET', 'operix-media')),
                'endpoint'                => env('MEDIA_S3_ENDPOINT', env('MINIO_ENDPOINT')),
                'use_path_style_endpoint' => env('MEDIA_S3_PATH_STYLE', true),
                'visibility'              => 'private',
                'throw'                   => true,
                // Cloudflare R2 (et MinIO/Spaces) ne supportent pas les checksums
                // d'intégrité CRC32 que le SDK AWS PHP récent envoie PAR DÉFAUT sur
                // chaque upload → l'écriture échouait. On ne calcule/valide les
                // checksums que lorsqu'ils sont explicitement requis.
                'request_checksum_calculation' => 'when_required',
                'response_checksum_validation' => 'when_required',
            ],
            default => [
                'driver'     => 'local',
                'root'       => storage_path('app/media'),
                'url'        => rtrim(env('APP_URL', 'http://localhost'), '/') . '/storage/media',
                'visibility' => 'private',
                'throw'      => true,
            ],
        },

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
