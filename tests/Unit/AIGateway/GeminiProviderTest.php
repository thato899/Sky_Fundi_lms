<?php

declare(strict_types=1);

namespace Tests\Unit\AIGateway;

use Core\AIGateway\Application\DTOs\AIRequest;
use Core\AIGateway\Exceptions\AIGatewayException;
use Core\AIGateway\Exceptions\ProviderNotAvailableException;
use Core\AIGateway\Infrastructure\Providers\GeminiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class GeminiProviderTest extends TestCase
{
    public function test_plain_completion_preserves_the_ordinary_payload_response_and_usage(): void
    {
        Http::fake([
            'https://api.gemini.test/models/gemini-test:generateContent' => Http::response([
                'candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => 'A concise educational answer.']]], 'finishReason' => 'STOP']],
                'usageMetadata' => ['promptTokenCount' => 12, 'candidatesTokenCount' => 7, 'totalTokenCount' => 19],
            ]),
        ]);

        $response = $this->provider()->complete(new AIRequest(
            prompt: 'Explain photosynthesis.',
            temperature: 0.4,
            maxTokens: 250,
        ));

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.gemini.test/models/gemini-test:generateContent'
                && $request->hasHeader('x-goog-api-key', 'test-only')
                && $payload === [
                    'contents' => [['role' => 'user', 'parts' => [['text' => 'Explain photosynthesis.']]]],
                    'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 250],
                ];
        });
        $this->assertSame('A concise educational answer.', $response->content);
        $this->assertSame('gemini', $response->provider);
        $this->assertSame('gemini-test', $response->model);
        $this->assertSame(12, $response->usage['prompt_tokens']);
        $this->assertSame(7, $response->usage['completion_tokens']);
        $this->assertSame(19, $response->usage['total_tokens']);
    }

    public function test_structured_completion_requests_json_and_returns_canonical_json_with_usage(): void
    {
        $grading = $this->gradingResponse();
        Http::fake([
            'https://api.gemini.test/models/gemini-test:generateContent' => Http::response([
                'candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => json_encode($grading, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)]]], 'finishReason' => 'STOP']],
                'usageMetadata' => ['promptTokenCount' => 120, 'candidatesTokenCount' => 90, 'totalTokenCount' => 210],
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
            $instruction = $payload['systemInstruction']['parts'][0]['text'] ?? '';

            return $payload['generationConfig']['responseMimeType'] === 'application/json'
                && $payload['contents'] === [['role' => 'user', 'parts' => [['text' => '{"marks_available":3,"learner_answer":"42"}']]]]
                && str_contains($instruction, 'Return one JSON object only.')
                && str_contains($instruction, 'Do not use Markdown')
                && str_contains($instruction, 'Include every required property')
                && str_contains($instruction, 'do not add additional properties')
                && str_contains($instruction, 'Keep awarded marks between zero and the provided maximum.')
                && str_contains($instruction, 'Never provide hidden chain-of-thought.')
                && str_contains($instruction, json_encode($schema, JSON_THROW_ON_ERROR));
        });
        $this->assertSame(json_encode($grading, JSON_THROW_ON_ERROR), $response->content);
        $this->assertSame('gemini', $response->provider);
        $this->assertSame(120, $response->usage['prompt_tokens']);
        $this->assertSame(90, $response->usage['completion_tokens']);
        $this->assertSame(210, $response->usage['total_tokens']);
    }

    public function test_invalid_structured_output_throws_a_safe_gateway_exception(): void
    {
        Http::fake([
            'https://api.gemini.test/models/gemini-test:generateContent' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'The learner should receive two marks.']]]]],
            ]),
        ]);

        try {
            $this->provider()->complete(new AIRequest(
                prompt: '{"marks_available":3}',
                metadata: ['json_schema' => $this->gradingSchema()],
            ));
            $this->fail('Invalid structured output was accepted.');
        } catch (AIGatewayException $exception) {
            $this->assertSame('Gemini returned invalid structured output.', $exception->getMessage());
            $this->assertStringNotContainsString('two marks', $exception->getMessage());
        }
    }

    #[DataProvider('fencedJsonProvider')]
    public function test_common_outer_markdown_fence_variations_are_removed(string $wrapped): void
    {
        Http::fake(['https://api.gemini.test/models/gemini-test:generateContent' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => $wrapped]]]]],
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
        Http::fake(['https://api.gemini.test/models/gemini-test:generateContent' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Result: {"result":true}']]]]],
        ])]);

        $this->expectException(AIGatewayException::class);
        $this->provider()->complete(new AIRequest(prompt: '{}', metadata: ['json_schema' => ['type' => 'object']]));
    }

    public function test_blocked_prompt_throws_a_safe_gateway_exception(): void
    {
        Http::fake(['https://api.gemini.test/models/gemini-test:generateContent' => Http::response([
            'promptFeedback' => ['blockReason' => 'SAFETY'],
        ])]);

        try {
            $this->provider()->complete(new AIRequest(prompt: 'Hi'));
            $this->fail('A safety-blocked prompt was accepted.');
        } catch (AIGatewayException $exception) {
            $this->assertSame('Gemini blocked this request: SAFETY.', $exception->getMessage());
        }
    }

    public function test_empty_candidate_text_throws_a_safe_gateway_exception(): void
    {
        Http::fake(['https://api.gemini.test/models/gemini-test:generateContent' => Http::response([
            'candidates' => [['content' => ['parts' => []], 'finishReason' => 'MAX_TOKENS']],
        ])]);

        $this->expectException(AIGatewayException::class);
        $this->provider()->complete(new AIRequest(prompt: 'Hi'));
    }

    public function test_successful_stream_yields_content_chunks(): void
    {
        Http::fake(['https://api.gemini.test/models/gemini-test:streamGenerateContent?alt=sse' => Http::response(
            "data: {\"candidates\":[{\"content\":{\"parts\":[{\"text\":\"Hello\"}]}}]}\n\n",
        )]);

        $this->assertSame(['Hello'], iterator_to_array($this->provider()->stream(new AIRequest(prompt: 'Hi'))));
    }

    #[DataProvider('failedStreamingStatusProvider')]
    public function test_failed_stream_throws_safe_gateway_exception(int $status): void
    {
        Http::fake(['https://api.gemini.test/models/gemini-test:streamGenerateContent?alt=sse' => Http::response('private provider response', $status)]);

        try {
            iterator_to_array($this->provider()->stream(new AIRequest(prompt: 'Hi')));
            $this->fail('Failed stream was accepted.');
        } catch (AIGatewayException $exception) {
            $this->assertSame("Gemini streaming request failed with status {$status}.", $exception->getMessage());
            $this->assertStringNotContainsString('private provider response', $exception->getMessage());
        }
    }

    public static function failedStreamingStatusProvider(): array
    {
        return [[401], [429], [500]];
    }

    public function test_malformed_stream_event_throws_gateway_exception(): void
    {
        Http::fake(['https://api.gemini.test/models/gemini-test:streamGenerateContent?alt=sse' => Http::response("data: not-json\n\n")]);

        $this->expectException(AIGatewayException::class);
        iterator_to_array($this->provider()->stream(new AIRequest(prompt: 'Hi')));
    }

    public function test_embed_returns_the_vector_from_a_separate_embedding_model(): void
    {
        Http::fake([
            'https://api.gemini.test/models/text-embedding-test:embedContent' => Http::response([
                'embedding' => ['values' => [0.1, -0.2, 0.35]],
            ]),
        ]);

        $vector = $this->provider()->embed('Explain photosynthesis.');

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.gemini.test/models/text-embedding-test:embedContent'
                && $payload === ['content' => ['parts' => [['text' => 'Explain photosynthesis.']]]];
        });
        $this->assertSame([0.1, -0.2, 0.35], $vector);
    }

    public function test_embed_with_no_usable_vector_throws_a_safe_gateway_exception(): void
    {
        Http::fake(['https://api.gemini.test/models/text-embedding-test:embedContent' => Http::response(['embedding' => ['values' => []]])]);

        $this->expectException(AIGatewayException::class);
        $this->provider()->embed('Hi');
    }

    public function test_unavailable_provider_throws_before_any_request(): void
    {
        Http::fake();
        $provider = new GeminiProvider([
            'base_url' => 'https://api.gemini.test',
            'api_key' => null,
            'model' => 'gemini-test',
            'timeout' => 20,
            'enabled' => true,
        ]);

        try {
            $provider->complete(new AIRequest(prompt: 'Hi'));
            $this->fail('An unavailable provider accepted a request.');
        } catch (ProviderNotAvailableException) {
            Http::assertNothingSent();
        }
    }

    private function provider(): GeminiProvider
    {
        return new GeminiProvider([
            'base_url' => 'https://api.gemini.test',
            'api_key' => 'test-only',
            'model' => 'gemini-test',
            'embedding_model' => 'text-embedding-test',
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
