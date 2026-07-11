<?php

declare(strict_types=1);

namespace Core\Storage\Infrastructure\Providers;

use Core\Storage\Contracts\FileStorageInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Wraps a Laravel local filesystem disk (see config/filesystems.php)
 * behind FileStorageInterface, so callers never touch the Storage
 * facade or know they're on local disk versus a future cloud driver.
 */
final class LocalFileStorage implements FileStorageInterface
{
    private readonly Filesystem $disk;

    public function __construct(private readonly string $diskName = 'local')
    {
        $this->disk = Storage::disk($this->diskName);
    }

    public function put(string $path, mixed $contents, array $options = []): string
    {
        $this->disk->put($path, $contents, $options);

        return $path;
    }

    public function get(string $path): string
    {
        return $this->disk->get($path);
    }

    public function exists(string $path): bool
    {
        return $this->disk->exists($path);
    }

    public function delete(string $path): bool
    {
        return $this->disk->delete($path);
    }

    public function url(string $path): ?string
    {
        return $this->diskName === 'public' ? $this->disk->url($path) : null;
    }

    public function size(string $path): int
    {
        return $this->disk->size($path);
    }

    public function driverName(): string
    {
        return "local:{$this->diskName}";
    }
}
