<?php

declare(strict_types=1);
use Core\Backup\Infrastructure\Targets\ConfigurationBackupTarget;
use Core\Backup\Infrastructure\Targets\DatabaseBackupTarget;
use Core\Backup\Infrastructure\Targets\LogsBackupTarget;
use Core\Backup\Infrastructure\Targets\StorageBackupTarget;

return [
    'destination' => env('BACKUP_DESTINATION', storage_path('app/backups')),

    'targets' => [
        DatabaseBackupTarget::class,
        StorageBackupTarget::class,
        ConfigurationBackupTarget::class,
        LogsBackupTarget::class,
    ],
];
