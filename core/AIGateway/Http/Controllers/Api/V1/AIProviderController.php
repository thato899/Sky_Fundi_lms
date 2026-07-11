<?php

declare(strict_types=1);

namespace Core\AIGateway\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\AIGateway\Application\AIManager;
use Core\AIGateway\Application\DTOs\AIRequest;
use Core\AIGateway\Application\ProviderRegistry;
use Core\AIGateway\Http\Requests\TestProviderRequest;
use Illuminate\Http\JsonResponse;

final class AIProviderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly AIManager $manager,
    ) {}

    public function index(): JsonResponse
    {
        $providers = collect($this->registry->all())->map(fn ($provider, $name) => [
            'name' => $name,
            'available' => $provider->isAvailable(),
            'default' => $name === config('ai.default_provider'),
        ])->values();

        return $this->ok(['data' => $providers]);
    }

    public function test(TestProviderRequest $request): JsonResponse
    {
        $response = $this->manager->complete(new AIRequest(
            prompt: $request->string('prompt')->value(),
            capability: 'admin_test',
            preferredProvider: $request->string('provider')->value(),
        ));

        return $this->ok(['data' => [
            'provider' => $response->provider,
            'model' => $response->model,
            'content' => $response->content,
            'usage' => $response->usage,
        ]]);
    }
}
