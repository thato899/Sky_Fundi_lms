<?php

declare(strict_types=1);

namespace Core\Storage\Contracts;

/**
 * The abstraction every Core service and future module depends on for
 * file persistence — never Laravel's Storage facade directly, and
 * never a cloud SDK directly. See core/Storage/README.md ("Create
 * service interfaces"). Local is implemented today; S3/Azure/GCS
 * drivers are added later by implementing this same interface — no
 * caller code changes when that happens.
 */
interface FileStorageInterface
{
    /**
     * @param  string|resource  $contents
     */
    public function put(string $path, mixed $contents, array $options = []): string;

    public function get(string $path): string;

    public function exists(string $path): bool;

    public function delete(string $path): bool;

    /**
     * Publicly-accessible URL for the file, or null if the underlying
     * disk doesn't support public URLs (e.g. a private local disk).
     */
    public function url(string $path): ?string;

    public function size(string $path): int;

    public function driverName(): string;
}
