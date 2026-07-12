<?php

declare(strict_types=1);

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Disks
    |--------------------------------------------------------------------------
    |
    | Core\Storage abstracts over these disks (see
    | docs/ai/ai-gateway.md-equivalent for storage: core/Storage/README.md).
    | Application code should depend on Core\Storage\Application\Contracts\
    | StorageManagerInterface rather than the Storage facade directly,
    | so swapping local -> s3/azure/gcs requires no module changes.
    |
    */
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        // Future disks — wired up when Core\Storage adds each driver.
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => (bool) env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        // Placeholder — see
        // Core\Storage\Infrastructure\Providers\AzureBlobFileStorage.
        // Driver key kept distinct ("azure") so
        // StorageProviderFactory can route to the right adapter once
        // a real Flysystem Azure driver is registered.
        'azure' => [
            'driver' => 'azure',
            'account_name' => env('AZURE_STORAGE_ACCOUNT'),
            'account_key' => env('AZURE_STORAGE_KEY'),
            'container' => env('AZURE_STORAGE_CONTAINER'),
            'url' => env('AZURE_STORAGE_URL'),
            'throw' => false,
        ],

        // Placeholder — see
        // Core\Storage\Infrastructure\Providers\GoogleCloudFileStorage.
        'gcs' => [
            'driver' => 'gcs',
            'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
            'key_file' => env('GOOGLE_CLOUD_KEY_FILE'),
            'bucket' => env('GOOGLE_CLOUD_STORAGE_BUCKET'),
            'url' => env('GOOGLE_CLOUD_STORAGE_URL'),
            'throw' => false,
        ],
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
