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

final class OpenAIProvider implements AIProviderInterface
{
    public function __construct(private readonly array $config) {}

    public function complete(AIRequest $request): AIResponse
    {
        $this->assertAvailable();
        $payload = [
            'model' => $this->config['model'],
            'instructions' => $request->metadata['instructions'] ?? 'Return only the requested concise educational result.',
            'input' => $request->prompt,
            'max_output_tokens' => $request->maxTokens,
        ];
        if (isset($request->metadata['json_schema'])) {
            $payload['text'] = ['format' => [
                'type' => 'json_schema',
                'name' => $request->metadata['schema_name'] ?? 'structured_result',
                'strict' => true,
                'schema' => $request->metadata['json_schema'],
            ]];
        }

        try {
            $response = Http::baseUrl($this->config['base_url'])
                ->withToken($this->config['api_key'])
                ->acceptJson()
                ->timeout((int) $this->config['timeout'])
                ->retry((int) $this->config['max_retries'], 250, throw: false)
                ->post('/responses', $payload);
        } catch (\Throwable $exception) {
            throw new AIGatewayException('OpenAI request could not be completed.', previous: $exception);
        }

        if ($response->failed()) {
            throw new AIGatewayException('OpenAI request failed with status '.$response->status().'.');
        }
        $body = $response->json();
        $content = (string) ($body['output_text'] ?? '');
        if ($content === '') {
            foreach (($body['output'] ?? []) as $output) {
                foreach (($output['content'] ?? []) as $part) {
                    if (($part['type'] ?? null) === 'output_text') {
                        $content .= (string) ($part['text'] ?? '');
                    }
                }
            }
        }
        if ($content === '') {
            throw new AIGatewayException('OpenAI returned no usable output.');
        }

        return new AIResponse($content, $this->name(), (string) $this->config['model'], $body['usage'] ?? [], $body);
    }

    public function stream(AIRequest $request): Generator
    {
        yield $this->complete($request)->content;
    }

    public function isAvailable(): bool
    {
        return (bool) ($this->config['enabled'] ?? false) && ! empty($this->config['api_key']);
    }

    public function name(): string
    {
        return 'openai';
    }

    private function assertAvailable(): void
    {
        if (! $this->isAvailable()) {
            throw ProviderNotAvailableException::forProvider($this->name());
        }
    }
}
