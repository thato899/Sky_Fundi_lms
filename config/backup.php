<?php

declare(strict_types=1);

return [
    'destination' => env('BACKUP_DESTINATION', storage_path('app/backups')),

    'targets' => [
        \Core\Backup\Infrastructure\Targets\DatabaseBackupTarget::class,
        \Core\Backup\Infrastructure\Targets\StorageBackupTarget::class,
        \Core\Backup\Infrastructure\Targets\ConfigurationBackupTarget::class,
        \Core\Backup\Infrastructure\Targets\LogsBackupTarget::class,
    ],
];
