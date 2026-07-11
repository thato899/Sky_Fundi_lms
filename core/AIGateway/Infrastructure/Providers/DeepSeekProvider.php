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
 * Hosted provider using DeepSeek's OpenAI-compatible chat completions
 * API. See docs/ai/ai-gateway.md.
 */
final class DeepSeekProvider implements AIProviderInterface
{
    public function __construct(private readonly array $config) {}

    public function complete(AIRequest $request): AIResponse
    {
        $this->assertAvailable();

        $response = Http::baseUrl($this->config['base_url'])
            ->withToken($this->config['api_key'])
            ->timeout((int) $this->config['timeout'])
            ->post('/chat/completions', [
                'model' => $this->config['model'],
                'messages' => [
                    ['role' => 'user', 'content' => $request->prompt],
                ],
                'temperature' => $request->temperature,
                'max_tokens' => $request->maxTokens,
                'stream' => false,
            ]);

        if ($response->failed()) {
            throw new AIGatewayException("DeepSeek request failed with status {$response->status()}: {$response->body()}");
        }

        $body = $response->json();

        return new AIResponse(
            content: (string) ($body['choices'][0]['message']['content'] ?? ''),
            provider: $this->name(),
            model: $this->config['model'],
            usage: $body['usage'] ?? [],
            raw: $body,
        );
    }

    public function stream(AIRequest $request): Generator
    {
        $this->assertAvailable();

        $response = Http::baseUrl($this->config['base_url'])
            ->withToken($this->config['api_key'])
            ->timeout((int) $this->config['timeout'])
            ->withOptions(['stream' => true])
            ->post('/chat/completions', [
                'model' => $this->config['model'],
                'messages' => [['role' => 'user', 'content' => $request->prompt]],
                'temperature' => $request->temperature,
                'max_tokens' => $request->maxTokens,
                'stream' => true,
            ]);

        $body = $response->toPsrResponse()->getBody();

        while (! $body->eof()) {
            $line = trim($this->readLine($body));

            if ($line === '' || ! str_starts_with($line, 'data:')) {
                continue;
            }

            $payload = trim(substr($line, 5));

            if ($payload === '[DONE]') {
                break;
            }

            $chunk = json_decode($payload, true);
            $delta = $chunk['choices'][0]['delta']['content'] ?? null;

            if ($delta !== null) {
                yield $delta;
            }
        }
    }

    public function isAvailable(): bool
    {
        return (bool) ($this->config['enabled'] ?? false) && ! empty($this->config['api_key']);
    }

    public function name(): string
    {
        return 'deepseek';
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
