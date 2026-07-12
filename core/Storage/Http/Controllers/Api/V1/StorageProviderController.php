<?php

declare(strict_types=1);

namespace Core\Storage\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Storage\Application\StorageProviderRegistry;
use Illuminate\Http\JsonResponse;

final class StorageProviderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly StorageProviderRegistry $registry,
    ) {}

    public function index(): JsonResponse
    {
        $disks = collect($this->registry->all())->map(fn ($disk, $name) => [
            'name' => $name,
            'driver' => $disk->driverName(),
            'available' => $disk->isAvailable(),
            'default' => $name === config('filesystems.default'),
        ])->values();

        return $this->ok(['data' => $disks]);
    }
}
