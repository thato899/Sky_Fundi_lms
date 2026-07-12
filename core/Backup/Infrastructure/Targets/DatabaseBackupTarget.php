<?php

declare(strict_types=1);

namespace Core\Backup\Infrastructure\Targets;

use Core\Backup\Contracts\BackupTargetInterface;
use Core\Backup\Exceptions\BackupFailedException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * SQLite: a plain file copy (the whole database *is* one file).
 * MySQL: shells out to `mysqldump` via Symfony Process using the
 * configured connection's credentials — never a bundled database
 * driver library, since backup tooling should work regardless of
 * which PHP MySQL extension is present. See core/Backup/README.md.
 */
final class DatabaseBackupTarget implements BackupTargetInterface
{
    public function name(): string
    {
        return 'database';
    }

    public function backup(string $destinationDirectory): string
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        return match ($config['driver'] ?? null) {
            'sqlite' => $this->backupSqlite($config, $destinationDirectory),
            'mysql' => $this->backupMysql($config, $destinationDirectory),
            default => throw BackupFailedException::forTarget($this->name(), "Unsupported database driver \"{$config['driver']}\"."),
        };
    }

    private function backupSqlite(array $config, string $destinationDirectory): string
    {
        $source = $config['database'];

        if ($source === ':memory:' || ! File::exists($source)) {
            throw BackupFailedException::forTarget($this->name(), 'SQLite database file not found (or in-memory).');
        }

        $destination = $destinationDirectory.'/database-'.now()->format('Ymd-His').'.sqlite';
        File::copy($source, $destination);

        return $destination;
    }

    private function backupMysql(array $config, string $destinationDirectory): string
    {
        $destination = $destinationDirectory.'/database-'.now()->format('Ymd-His').'.sql';

        $result = Process::env(['MYSQL_PWD' => $config['password'] ?? ''])->run([
            'mysqldump',
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? '3306'),
            '--user='.($config['username'] ?? ''),
            $config['database'] ?? '',
        ]);

        if ($result->failed()) {
            throw BackupFailedException::forTarget($this->name(), $result->errorOutput() ?: 'mysqldump exited with a non-zero status.');
        }

        File::put($destination, $result->output());

        return $destination;
    }
}
