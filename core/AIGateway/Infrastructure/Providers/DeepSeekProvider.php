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
use Psr\Http\Message\StreamInterface;

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

        $structured = isset($request->metadata['json_schema']);
        $payload = [
            'model' => $this->config['model'],
            'messages' => [
                ['role' => 'user', 'content' => $request->prompt],
            ],
            'temperature' => $request->temperature,
            'max_tokens' => $request->maxTokens,
            'stream' => false,
        ];

        if ($structured) {
            $payload['messages'] = [
                ['role' => 'system', 'content' => $this->structuredOutputInstruction($request)],
                ['role' => 'user', 'content' => $request->prompt],
            ];
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::baseUrl($this->config['base_url'])
            ->withToken($this->config['api_key'])
            ->timeout((int) $this->config['timeout'])
            ->post('/chat/completions', $payload);

        if ($response->failed()) {
            throw new AIGatewayException("DeepSeek request failed with status {$response->status()}: {$response->body()}");
        }

        $body = $response->json();
        $content = (string) ($body['choices'][0]['message']['content'] ?? '');

        if ($structured) {
            $content = $this->canonicalStructuredOutput($content);
        }

        return new AIResponse(
            content: $content,
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

    private function structuredOutputInstruction(AIRequest $request): string
    {
        try {
            $schema = json_encode(
                $request->metadata['json_schema'],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (\JsonException $exception) {
            throw new AIGatewayException('DeepSeek structured output schema is invalid.', previous: $exception);
        }

        return implode("\n", [
            (string) ($request->metadata['instructions'] ?? 'Return the requested concise educational result.'),
            'Return one JSON object only. Do not use Markdown or wrap the output in code fences.',
            'Follow the supplied schema exactly. Include every required property and do not add additional properties.',
            'Use the correct primitive types. Keep awarded marks between zero and the provided maximum.',
            'Never provide hidden chain-of-thought. Return only concise educational rationale.',
            'JSON Schema: '.$schema,
        ]);
    }

    private function canonicalStructuredOutput(string $content): string
    {
        $content = trim($content);

        if (str_starts_with($content, "```json\n") && str_ends_with($content, "\n```")) {
            $content = trim(substr($content, 8, -4));
        }

        try {
            $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($decoded)) {
                throw new \JsonException('Structured output was not an object or array.');
            }

            return json_encode(
                $decoded,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (\JsonException $exception) {
            throw new AIGatewayException('DeepSeek returned invalid structured output.', previous: $exception);
        }
    }

    private function readLine(StreamInterface $stream): string
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
