<?php

declare(strict_types=1);

namespace Core\AIGateway\Application;

use Core\AIGateway\Application\DTOs\AIRequest;
use Core\AIGateway\Application\DTOs\AIResponse;
use Core\AIGateway\Exceptions\AIGatewayException;
use Core\AIGateway\Exceptions\ProviderNotAvailableException;
use Core\Analytics\Application\AnalyticsRecorder;
use Core\Analytics\Domain\Enums\AnalyticsMetric;
use Core\Logging\Application\PlatformLogger;
use Generator;

/**
 * The single entry point every Core service and module uses for AI
 * capability — see docs/ai/ai-gateway.md. Never inject a provider
 * class directly; always depend on AIManager.
 *
 * Provider selection order, per docs/ai/ai-gateway.md: explicit request
 * preference -> platform default (config('ai.default_provider')) ->
 * configured fallback_provider on failure.
 */
final class AIManager
{
    public function __construct(
        private readonly ProviderFactory $factory,
        private readonly PlatformLogger $logger,
        private readonly AnalyticsRecorder $analytics,
    ) {}

    public function complete(AIRequest $request): AIResponse
    {
        $providerName = $this->resolveProviderName($request);

        try {
            $provider = $this->factory->make($providerName);
            $response = $provider->complete($request);

            $this->logger->ai('info', 'ai.completion', [
                'provider' => $providerName,
                'capability' => $request->capability,
                'module_id' => $request->moduleId,
                'tenant_id' => $request->tenantId,
            ]);

            $this->analytics->record(AnalyticsMetric::AIUsage, value: 1.0, metadata: [
                'provider' => $response->provider,
                'model' => $response->model,
                'capability' => $request->capability,
            ]);

            return $response;
        } catch (ProviderNotAvailableException|AIGatewayException $e) {
            return $this->attemptFallback($request, $providerName, $e);
        }
    }

    /**
     * @return Generator<int, string>
     */
    public function stream(AIRequest $request): Generator
    {
        $providerName = $this->resolveProviderName($request);
        $provider = $this->factory->make($providerName);

        yield from $provider->stream($request);
    }

    private function resolveProviderName(AIRequest $request): string
    {
        return $request->preferredProvider ?? (string) config('ai.default_provider');
    }

    /**
     * @throws AIGatewayException
     */
    private function attemptFallback(AIRequest $request, string $failedProvider, \Throwable $original): AIResponse
    {
        $fallback = config('ai.fallback_provider');

        $this->logger->ai('warning', 'ai.provider_failed', [
            'provider' => $failedProvider,
            'fallback' => $fallback,
            'error' => $original->getMessage(),
        ]);

        if (! $fallback || $fallback === $failedProvider) {
            throw new AIGatewayException(
                "AI provider \"{$failedProvider}\" is unavailable and no usable fallback is configured: {$original->getMessage()}",
                previous: $original,
            );
        }

        $provider = $this->factory->make($fallback);

        return $provider->complete($request);
    }
}
