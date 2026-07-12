<?php

declare(strict_types=1);

namespace Core\Backup\Contracts;

/**
 * One backup target — database, storage, configuration, or logs (per
 * the brief). Each target produces a single archive file under the
 * given destination directory and returns its path. Restore is
 * explicitly out of scope for every implementation — see
 * core/Backup/README.md ("Future Restore").
 */
interface BackupTargetInterface
{
    public function name(): string;

    /**
     * @throws \Core\Backup\Exceptions\BackupFailedException
     */
    public function backup(string $destinationDirectory): string;
}
