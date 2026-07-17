<?php

declare(strict_types=1);

namespace Tests\Feature\ApiContract;

use Core\Support\Exceptions\DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class ApiErrorContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix('api/v1/contract-fixtures')->group(function (): void {
            Route::post('/validated', function (Request $request): array {
                return $request->validate(['name' => ['required', 'string']]);
            });
            Route::get('/domain-conflict', fn () => throw new DomainException('The requested lifecycle transition is not allowed.'));
            Route::get('/unexpected', fn () => throw new RuntimeException('internal fixture detail'));
        });
    }

    public function test_validation_errors_use_the_stable_json_contract(): void
    {
        $this->postJson('/api/v1/contract-fixtures/validated')
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.message', 'The given data was invalid.')
            ->assertJsonStructure(['error' => ['code', 'message', 'details' => ['name']], 'errors' => ['name']]);
    }

    public function test_authentication_and_missing_routes_use_safe_machine_readable_errors(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('error.code', 'unauthenticated')
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('trace');

        $this->getJson('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('error.code', 'not_found')
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('trace');
    }

    public function test_domain_errors_preserve_safe_details_without_internal_context(): void
    {
        $this->getJson('/api/v1/contract-fixtures/domain-conflict')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'domain_rule_violation')
            ->assertJsonPath('error.message', 'The requested lifecycle transition is not allowed.')
            ->assertJsonMissingPath('error.details')
            ->assertJsonMissingPath('trace');
    }

    public function test_method_not_allowed_and_malformed_json_remain_json(): void
    {
        $this->deleteJson('/api/v1/contract-fixtures/validated')
            ->assertStatus(405)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('error.code', 'http_error');

        $this->call(
            'POST',
            '/api/v1/contract-fixtures/validated',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: '{"name":',
        )
            ->assertBadRequest()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('error.code', 'malformed_json')
            ->assertJsonPath('error.message', 'The request body contains malformed JSON.')
            ->assertJsonMissingPath('trace');
    }

    public function test_unexpected_production_errors_do_not_expose_debug_details(): void
    {
        config()->set('app.debug', false);

        $response = $this->getJson('/api/v1/contract-fixtures/unexpected')
            ->assertInternalServerError()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('error.code', 'server_error')
            ->assertJsonPath('error.message', 'An unexpected error occurred.')
            ->assertJsonStructure(['error' => ['request_id']])
            ->assertJsonMissingPath('error.details')
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('trace');

        $this->assertTrue(Str::isUuid($response->headers->get('X-Request-ID')));
        $this->assertSame($response->headers->get('X-Request-ID'), $response->json('error.request_id'));
        $this->assertStringNotContainsString('internal fixture detail', $response->getContent());
        $this->assertStringNotContainsString(base_path(), $response->getContent());
    }

    public function test_unexpected_errors_remain_generic_when_debug_is_enabled(): void
    {
        config()->set('app.debug', true);

        $response = $this->getJson('/api/v1/contract-fixtures/unexpected')
            ->assertInternalServerError()
            ->assertJsonPath('error.message', 'An unexpected error occurred.')
            ->assertJsonMissingPath('error.details')
            ->assertJsonMissingPath('trace');

        $this->assertStringNotContainsString('internal fixture detail', $response->getContent());
        $this->assertStringNotContainsString(base_path(), $response->getContent());
    }
}
