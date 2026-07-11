<?php

declare(strict_types=1);

namespace Core\AIGateway\Infrastructure\Providers;

use Core\AIGateway\Application\DTOs\AIRequest;
use Core\AIGateway\Application\DTOs\AIResponse;
use Core\AIGateway\Contracts\AIProviderInterface;
use Core\AIGateway\Exceptions\AIGatewayException;
use Core\AIGateway\Exceptions\ProviderNotAvailableException;
use Generator;
use Illuminate\Support\Facades\Http;

/**
 * Self-hosted / offline provider via the Ollama HTTP API
 * (https://github.com/ollama/ollama/blob/main/docs/api.md). The
 * platform's primary offline-capable provider — see
 * docs/ai/ai-gateway.md and the v2.0 "Offline AI" roadmap item.
 */
final class OllamaProvider implements AIProviderInterface
{
    public function __construct(private readonly array $config) {}

    public function complete(AIRequest $request): AIResponse
    {
        $this->assertAvailable();

        $response = Http::baseUrl($this->config['base_url'])
            ->timeout((int) $this->config['timeout'])
            ->post('/api/generate', [
                'model' => $this->config['model'],
                'prompt' => $request->prompt,
                'stream' => false,
                'options' => [
                    'temperature' => $request->temperature,
                    'num_predict' => $request->maxTokens,
                ],
            ]);

        if ($response->failed()) {
            throw new AIGatewayException("Ollama request failed with status {$response->status()}: {$response->body()}");
        }

        $body = $response->json();

        return new AIResponse(
            content: (string) ($body['response'] ?? ''),
            provider: $this->name(),
            model: $this->config['model'],
            usage: [
                'prompt_eval_count' => $body['prompt_eval_count'] ?? null,
                'eval_count' => $body['eval_count'] ?? null,
            ],
            raw: $body,
        );
    }

    public function stream(AIRequest $request): Generator
    {
        $this->assertAvailable();

        $response = Http::baseUrl($this->config['base_url'])
            ->timeout((int) $this->config['timeout'])
            ->withOptions(['stream' => true])
            ->post('/api/generate', [
                'model' => $this->config['model'],
                'prompt' => $request->prompt,
                'stream' => true,
                'options' => ['temperature' => $request->temperature, 'num_predict' => $request->maxTokens],
            ]);

        $body = $response->toPsrResponse()->getBody();

        while (! $body->eof()) {
            $line = trim($this->readLine($body));

            if ($line === '') {
                continue;
            }

            $chunk = json_decode($line, true);

            if (isset($chunk['response'])) {
                yield $chunk['response'];
            }
        }
    }

    public function isAvailable(): bool
    {
        return (bool) ($this->config['enabled'] ?? false) && ! empty($this->config['base_url']);
    }

    public function name(): string
    {
        return 'ollama';
    }

    private function assertAvailable(): void
    {
        if (! $this->isAvailable()) {
            throw ProviderNotAvailableException::forProvider($this->name());
        }
    }

    private function readLine(\Psr\Http\Message\StreamInterface $stream): string
    {
        $line = '';

        while (! $stream->eof()) {
            $char = $stream->read(1);

            if ($char === "\n") {
                break;
            }

            $line .= $char;
        }

        return $line;
    }
}
