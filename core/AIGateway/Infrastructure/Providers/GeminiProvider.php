<?php

declare(strict_types=1);

namespace Core\AIGateway\Infrastructure\Providers;

use Core\AIGateway\Application\DTOs\AIRequest;
use Core\AIGateway\Application\DTOs\AIResponse;
use Core\AIGateway\Contracts\AIProviderInterface;
use Core\AIGateway\Contracts\EmbeddingProviderInterface;
use Core\AIGateway\Exceptions\AIGatewayException;
use Core\AIGateway\Exceptions\ProviderNotAvailableException;
use Generator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;

/**
 * Real Gemini integration against Google's Generative Language API
 * (`{base_url}/models/{model}:generateContent`), authenticated via the
 * `x-goog-api-key` header rather than a query-string key so it never
 * ends up in access logs. See docs/ai/ai-gateway.md.
 *
 * Structured output uses `generationConfig.responseMimeType =
 * "application/json"` plus a system instruction describing the schema
 * — the same strategy as Core\AIGateway\Infrastructure\Providers\DeepSeekProvider
 * — rather than Gemini's native `responseSchema`, which uses its own
 * upper-case OpenAPI-subset type vocabulary (STRING/OBJECT/ARRAY/...)
 * that would need translating from every caller's plain JSON Schema
 * (lower-case types) before use; a translator wrong on one nested case
 * fails silently as a malformed schema, whereas responseMimeType only
 * has to guarantee valid JSON syntax, which every caller already
 * parses defensively.
 *
 * Also the only provider implementing EmbeddingProviderInterface —
 * see docs/adr/011-materials-retrieval.md — against a separate
 * embedding model (`config('ai.providers.gemini.embedding_model')`,
 * distinct from the completion model) via `models/{model}:embedContent`.
 */
final class GeminiProvider implements AIProviderInterface, EmbeddingProviderInterface
{
    public function __construct(private readonly array $config) {}

    public function complete(AIRequest $request): AIResponse
    {
        $this->assertAvailable();

        $structured = isset($request->metadata['json_schema']);
        $payload = [
            'contents' => [['role' => 'user', 'parts' => [['text' => $request->prompt]]]],
            'generationConfig' => [
                'temperature' => $request->temperature,
                'maxOutputTokens' => $request->maxTokens,
            ],
        ];

        if ($structured) {
            $payload['systemInstruction'] = ['parts' => [['text' => $this->structuredOutputInstruction($request)]]];
            $payload['generationConfig']['responseMimeType'] = 'application/json';
        }

        $response = $this->client()->post("/models/{$this->config['model']}:generateContent", $payload);

        if ($response->failed()) {
            throw new AIGatewayException("Gemini request failed with status {$response->status()}.");
        }

        $body = $response->json();
        $content = $this->extractText($body);

        if ($structured) {
            $content = $this->canonicalStructuredOutput($content);
        }

        return new AIResponse(
            content: $content,
            provider: $this->name(),
            model: (string) $this->config['model'],
            usage: $this->normalizedUsage($body),
            raw: $body,
        );
    }

    public function stream(AIRequest $request): Generator
    {
        $this->assertAvailable();

        $response = $this->client()
            ->withOptions(['stream' => true])
            ->post("/models/{$this->config['model']}:streamGenerateContent?alt=sse", [
                'contents' => [['role' => 'user', 'parts' => [['text' => $request->prompt]]]],
                'generationConfig' => [
                    'temperature' => $request->temperature,
                    'maxOutputTokens' => $request->maxTokens,
                ],
            ]);

        if ($response->failed()) {
            throw new AIGatewayException("Gemini streaming request failed with status {$response->status()}.");
        }

        $body = $response->toPsrResponse()->getBody();
        while (! $body->eof()) {
            $line = trim($this->readLine($body));

            if ($line === '' || ! str_starts_with($line, 'data:')) {
                continue;
            }

            $payload = trim(substr($line, 5));

            try {
                $chunk = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new AIGatewayException('Gemini returned a malformed streaming response.', previous: $exception);
            }

            $delta = $chunk['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if ($delta !== null) {
                yield $delta;
            }
        }
    }

    public function embed(string $text): array
    {
        $this->assertAvailable();
        $model = $this->config['embedding_model'] ?? $this->config['model'];

        $response = $this->client()->post("/models/{$model}:embedContent", [
            'content' => ['parts' => [['text' => $text]]],
        ]);

        if ($response->failed()) {
            throw new AIGatewayException("Gemini embedding request failed with status {$response->status()}.");
        }

        $values = $response->json('embedding.values');
        if (! is_array($values) || $values === []) {
            throw new AIGatewayException('Gemini returned no usable embedding.');
        }

        return array_map(static fn (mixed $value): float => (float) $value, $values);
    }

    public function isAvailable(): bool
    {
        return (bool) ($this->config['enabled'] ?? false) && ! empty($this->config['api_key']);
    }

    public function name(): string
    {
        return 'gemini';
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->config['base_url'])
            ->withHeaders(['x-goog-api-key' => $this->config['api_key']])
            ->acceptJson()
            ->timeout((int) $this->config['timeout']);
    }

    private function assertAvailable(): void
    {
        if (! $this->isAvailable()) {
            throw ProviderNotAvailableException::forProvider($this->name());
        }
    }

    /**
     * @throws AIGatewayException when the prompt/response was blocked
     *                            by Gemini's safety filters or no candidate text was returned
     */
    private function extractText(array $body): string
    {
        $blockReason = $body['promptFeedback']['blockReason'] ?? null;
        if ($blockReason !== null) {
            throw new AIGatewayException("Gemini blocked this request: {$blockReason}.");
        }

        $parts = $body['candidates'][0]['content']['parts'] ?? [];
        $content = implode('', array_map(fn (array $part): string => (string) ($part['text'] ?? ''), $parts));

        if ($content === '') {
            $finishReason = $body['candidates'][0]['finishReason'] ?? 'unknown';
            throw new AIGatewayException("Gemini returned no usable output (finish reason: {$finishReason}).");
        }

        return $content;
    }

    private function normalizedUsage(array $body): array
    {
        $usage = $body['usageMetadata'] ?? [];

        return [
            'prompt_tokens' => $usage['promptTokenCount'] ?? null,
            'completion_tokens' => $usage['candidatesTokenCount'] ?? null,
            'total_tokens' => $usage['totalTokenCount'] ?? null,
        ];
    }

    private function structuredOutputInstruction(AIRequest $request): string
    {
        try {
            $schema = json_encode(
                $request->metadata['json_schema'],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (\JsonException $exception) {
            throw new AIGatewayException('Gemini structured output schema is invalid.', previous: $exception);
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

        if (preg_match('/\A```(?:json)?[ \t]*\R(.*)\R```[ \t]*\z/is', $content, $matches) === 1) {
            $content = trim($matches[1]);
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
            throw new AIGatewayException('Gemini returned invalid structured output.', previous: $exception);
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
