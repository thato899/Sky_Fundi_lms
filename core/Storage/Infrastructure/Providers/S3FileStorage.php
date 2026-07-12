<?php

declare(strict_types=1);

namespace Core\Storage\Infrastructure\Providers;

use Core\Storage\Contracts\FileStorageInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Wraps the Laravel 's3' filesystem disk (config/filesystems.php)
 * behind FileStorageInterface. Requires the
 * "league/flysystem-aws-s3-v3" package (declared in composer.json)
 * and AWS_* credentials — see docs/environment-variables.md. Fully
 * implemented, same as Core\Storage\Infrastructure\Providers\
 * LocalFileStorage; not a placeholder.
 */
final class S3FileStorage implements FileStorageInterface
{
    private readonly Filesystem $disk;

    public function __construct(private readonly string $diskName = 's3')
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
        return $this->disk->url($path);
    }

    public function size(string $path): int
    {
        return $this->disk->size($path);
    }

    public function driverName(): string
    {
        return "s3:{$this->diskName}";
    }

    public function isAvailable(): bool
    {
        return filled(config("filesystems.disks.{$this->diskName}.key"))
            && filled(config("filesystems.disks.{$this->diskName}.bucket"));
    }
}
