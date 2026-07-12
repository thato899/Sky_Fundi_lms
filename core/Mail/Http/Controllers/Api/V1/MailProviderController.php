<?php

declare(strict_types=1);

namespace Core\Mail\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Mail\Application\MailProviderRegistry;
use Illuminate\Http\JsonResponse;

final class MailProviderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly MailProviderRegistry $registry,
    ) {}

    public function index(): JsonResponse
    {
        $providers = collect($this->registry->all())->map(fn ($provider, $name) => [
            'name' => $name,
            'available' => $provider->isAvailable(),
            'default' => $name === config('mail_providers.default_provider'),
        ])->values();

        return $this->ok(['data' => $providers]);
    }
}
