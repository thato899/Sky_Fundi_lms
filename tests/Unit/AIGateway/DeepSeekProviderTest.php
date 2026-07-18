<?php

declare(strict_types=1);

namespace Tests\Unit\AIGateway;

use Core\AIGateway\Application\DTOs\AIRequest;
use Core\AIGateway\Exceptions\AIGatewayException;
use Core\AIGateway\Infrastructure\Providers\DeepSeekProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class DeepSeekProviderTest extends TestCase
{
    public function test_plain_completion_preserves_the_ordinary_payload_response_and_usage(): void
    {
        Http::fake([
            'https://api.deepseek.test/chat/completions' => Http::response([
                'id' => 'completion-1',
                'choices' => [['message' => ['content' => 'A concise educational answer.']]],
                'usage' => [
                    'prompt_tokens' => 12,
                    'completion_tokens' => 7,
                    'total_tokens' => 19,
                    'prompt_cache_hit_tokens' => 4,
                    'prompt_cache_miss_tokens' => 8,
                ],
            ]),
        ]);

        $response = $this->provider()->complete(new AIRequest(
            prompt: 'Explain photosynthesis.',
            temperature: 0.4,
            maxTokens: 250,
        ));

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.deepseek.test/chat/completions'
                && $payload === [
                    'model' => 'deepseek-chat',
                    'messages' => [['role' => 'user', 'content' => 'Explain photosynthesis.']],
                    'temperature' => 0.4,
                    'max_tokens' => 250,
                    'stream' => false,
                ];
        });
        $this->assertSame('A concise educational answer.', $response->content);
        $this->assertSame('deepseek', $response->provider);
        $this->assertSame('deepseek-chat', $response->model);
        $this->assertSame(12, $response->usage['prompt_tokens']);
        $this->assertSame(7, $response->usage['completion_tokens']);
        $this->assertSame(19, $response->usage['total_tokens']);
        $this->assertSame(4, $response->usage['prompt_cache_hit_tokens']);
        $this->assertSame('completion-1', $response->raw['id']);
    }

    public function test_structured_completion_requests_json_and_returns_canonical_json_with_usage(): void
    {
        $grading = $this->gradingResponse();
        Http::fake([
            'https://api.deepseek.test/chat/completions' => Http::response([
                'id' => 'completion-2',
                'choices' => [['message' => ['role' => 'assistant', 'content' => json_encode($grading, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)]]],
                'usage' => [
                    'prompt_tokens' => 120,
                    'completion_tokens' => 90,
                    'total_tokens' => 210,
                    'prompt_cache_hit_tokens' => 30,
                    'prompt_cache_miss_tokens' => 90,
                ],
            ]),
        ]);

        $schema = $this->gradingSchema();
        $response = $this->provider()->complete(new AIRequest(
            prompt: '{"marks_available":3,"learner_answer":"42"}',
            capability: 'assessment.written_marking',
            temperature: 0.1,
            maxTokens: 1000,
            metadata: [
                'instructions' => 'Grade only against the supplied rubric.',
                'json_schema' => $schema,
            ],
        ));

        Http::assertSent(function (Request $request) use ($schema): bool {
            $payload = $request->data();
            $instruction = $payload['messages'][0]['content'] ?? '';

            return $payload['response_format'] === ['type' => 'json_object']
                && $payload['messages'][0]['role'] === 'system'
                && $payload['messages'][1] === ['role' => 'user', 'content' => '{"marks_available":3,"learner_answer":"42"}']
                && str_contains($instruction, 'Return one JSON object only.')
                && str_contains($instruction, 'Do not use Markdown')
                && str_contains($instruction, 'Include every required property')
                && str_contains($instruction, 'do not add additional properties')
                && str_contains($instruction, 'Keep awarded marks between zero and the provided maximum.')
                && str_contains($instruction, 'Never provide hidden chain-of-thought.')
                && str_contains($instruction, json_encode($schema, JSON_THROW_ON_ERROR));
        });
        $this->assertSame(json_encode($grading, JSON_THROW_ON_ERROR), $response->content);
        $this->assertSame('deepseek', $response->provider);
        $this->assertSame('deepseek-chat', $response->model);
        $this->assertSame(120, $response->usage['prompt_tokens']);
        $this->assertSame(90, $response->usage['completion_tokens']);
        $this->assertSame(210, $response->usage['total_tokens']);
        $this->assertSame(30, $response->usage['prompt_cache_hit_tokens']);
        $this->assertSame(90, $response->usage['prompt_cache_miss_tokens']);
        $this->assertSame('completion-2', $response->raw['id']);
    }

    public function test_invalid_structured_output_throws_a_safe_gateway_exception(): void
    {
        Http::fake([
            'https://api.deepseek.test/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'The learner should receive two marks.']]],
            ]),
        ]);

        try {
            $this->provider()->complete(new AIRequest(
                prompt: '{"marks_available":3}',
                metadata: ['json_schema' => $this->gradingSchema()],
            ));
            $this->fail('Invalid structured output was accepted.');
        } catch (AIGatewayException $exception) {
            $this->assertSame('DeepSeek returned invalid structured output.', $exception->getMessage());
            $this->assertStringNotContainsString('two marks', $exception->getMessage());
        }
    }

    public function test_single_outer_json_markdown_fence_is_removed_defensively(): void
    {
        $grading = $this->gradingResponse();
        $json = json_encode($grading, JSON_THROW_ON_ERROR);
        Http::fake([
            'https://api.deepseek.test/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => "```json\n{$json}\n```"]]],
            ]),
        ]);

        $response = $this->provider()->complete(new AIRequest(
            prompt: '{"marks_available":3}',
            metadata: ['json_schema' => $this->gradingSchema()],
        ));

        $this->assertSame($json, $response->content);
    }

    #[DataProvider('fencedJsonProvider')]
    public function test_common_outer_markdown_fence_variations_are_removed(string $wrapped): void
    {
        Http::fake(['https://api.deepseek.test/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => $wrapped]]],
        ])]);

        $response = $this->provider()->complete(new AIRequest(
            prompt: '{}',
            metadata: ['json_schema' => ['type' => 'object']],
        ));

        $this->assertSame('{"result":true}', $response->content);
    }

    public static function fencedJsonProvider(): array
    {
        return [
            'lowercase json' => ["```json\n{\"result\":true}\n```"],
            'uppercase JSON' => ["```JSON\n{\"result\":true}\n```"],
            'no language' => ["```\n{\"result\":true}\n```"],
            'CRLF and whitespace' => [" \r\n```json\r\n{\"result\":true}\r\n```\r\n "],
            'unfenced' => [" \n{\"result\":true}\n "],
        ];
    }

    public function test_prose_around_structured_json_remains_invalid(): void
    {
        Http::fake(['https://api.deepseek.test/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'Result: {"result":true}']]],
        ])]);

        $this->expectException(AIGatewayException::class);
        $this->provider()->complete(new AIRequest(prompt: '{}', metadata: ['json_schema' => ['type' => 'object']]));
    }

    public function test_successful_stream_yields_content_chunks(): void
    {
        Http::fake(['https://api.deepseek.test/chat/completions' => Http::response(
            "data: {\"choices\":[{\"delta\":{\"content\":\"Hello\"}}]}\n\ndata: [DONE]\n",
        )]);

        $this->assertSame(['Hello'], iterator_to_array($this->provider()->stream(new AIRequest(prompt: 'Hi'))));
    }

    #[DataProvider('failedStreamingStatusProvider')]
    public function test_failed_stream_throws_safe_gateway_exception(int $status): void
    {
        Http::fake(['https://api.deepseek.test/chat/completions' => Http::response('private provider response', $status)]);

        try {
            iterator_to_array($this->provider()->stream(new AIRequest(prompt: 'Hi')));
            $this->fail('Failed stream was accepted.');
        } catch (AIGatewayException $exception) {
            $this->assertSame("DeepSeek streaming request failed with status {$status}.", $exception->getMessage());
            $this->assertStringNotContainsString('private provider response', $exception->getMessage());
        }
    }

    public static function failedStreamingStatusProvider(): array
    {
        return [[401], [429], [500]];
    }

    public function test_malformed_stream_event_throws_gateway_exception(): void
    {
        Http::fake(['https://api.deepseek.test/chat/completions' => Http::response("data: not-json\n")]);

        $this->expectException(AIGatewayException::class);
        iterator_to_array($this->provider()->stream(new AIRequest(prompt: 'Hi')));
    }

    private function provider(): DeepSeekProvider
    {
        return new DeepSeekProvider([
            'base_url' => 'https://api.deepseek.test',
            'api_key' => 'test-only',
            'model' => 'deepseek-chat',
            'timeout' => 20,
            'enabled' => true,
        ]);
    }

    private function gradingResponse(): array
    {
        return [
            'awarded_marks' => 2,
            'max_marks' => 3,
            'criteria' => [
                [
                    'criterion' => 'Correct formula',
                    'met' => true,
                    'marks_awarded' => 1,
                ],
            ],
            'strengths' => [
                'The learner calculated the correct numerical answer.',
            ],
            'improvements' => [
                'Show the substitution step explicitly.',
            ],
            'misconceptions' => [],
            'grading_rationale' => 'The answer is correct, but one rubric step was not shown.',
            'confidence' => 0.93,
            'requires_teacher_review' => true,
        ];
    }

    private function gradingSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['awarded_marks', 'max_marks', 'criteria', 'strengths', 'improvements', 'misconceptions', 'grading_rationale', 'confidence', 'requires_teacher_review'],
            'properties' => [
                'awarded_marks' => ['type' => 'number'],
                'max_marks' => ['type' => 'number'],
                'criteria' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['criterion', 'met', 'marks_awarded'],
                        'properties' => [
                            'criterion' => ['type' => 'string'],
                            'met' => ['type' => 'boolean'],
                            'marks_awarded' => ['type' => 'number'],
                        ],
                    ],
                ],
                'strengths' => ['type' => 'array', 'items' => ['type' => 'string']],
                'improvements' => ['type' => 'array', 'items' => ['type' => 'string']],
                'misconceptions' => ['type' => 'array', 'items' => ['type' => 'string']],
                'grading_rationale' => ['type' => 'string'],
                'confidence' => ['type' => 'number'],
                'requires_teacher_review' => ['type' => 'boolean'],
            ],
        ];
    }
}
