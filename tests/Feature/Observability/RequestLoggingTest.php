<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use Core\Api\Http\Middleware\LogApiRequests;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class RequestLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_completion_log_contains_safe_structured_request_context(): void
    {
        $channel = Mockery::mock(LoggerInterface::class);
        Log::shouldReceive('channel')->with('application')->andReturn($channel);
        Log::shouldReceive('error')->zeroOrMoreTimes();
        $channel->shouldReceive('log')
            ->once()
            ->withArgs(function (string $level, string $message, array $context): bool {
                $this->assertSame('info', $level);
                $this->assertSame('http.request.completed', $message);
                $this->assertSame('GET', $context['method']);
                $this->assertSame('/', $context['path']);
                $this->assertSame(200, $context['status']);
                $this->assertArrayHasKey('duration_ms', $context);
                $this->assertArrayHasKey('request_id', $context);
                $this->assertArrayNotHasKey('authorization', $context);
                $this->assertArrayNotHasKey('cookie', $context);
                $this->assertArrayNotHasKey('body', $context);

                return true;
            });

        $this->get('/')->assertOk();
    }

    public function test_a_zero_slow_request_threshold_disables_slow_warnings(): void
    {
        config()->set('observability.slow_request_ms', 0);

        $channel = Mockery::mock(LoggerInterface::class);
        Log::shouldReceive('channel')->with('application')->andReturn($channel);
        Log::shouldReceive('error')->zeroOrMoreTimes();
        $channel->shouldReceive('log')
            ->once()
            ->with('info', 'http.request.completed', Mockery::on(
                static fn (array $context): bool => $context['outcome'] === 'completed',
            ));

        $this->get('/')->assertOk();
    }

    public function test_a_slow_request_produces_a_structured_warning_without_sensitive_input(): void
    {
        config()->set('observability.slow_request_ms', 1);

        $channel = Mockery::mock(LoggerInterface::class);
        Log::shouldReceive('channel')->with('application')->andReturn($channel);
        Log::shouldReceive('error')->zeroOrMoreTimes();
        $channel->shouldReceive('log')
            ->once()
            ->withArgs(static fn (string $level, string $message, array $context): bool => $level === 'warning'
                && $message === 'http.request.completed'
                && $context['outcome'] === 'slow'
                && ! array_key_exists('query', $context)
                && ! array_key_exists('body', $context));

        $request = Request::create('/?password=must-not-be-logged', server: [
            'HTTP_AUTHORIZATION' => 'Bearer must-not-be-logged',
        ]);
        $request->setRouteResolver(static fn (): Route => new Route('GET', '/', static fn (): null => null));

        app(LogApiRequests::class)->handle($request, static function (): Response {
            $finishAt = hrtime(true) + 2_000_000;

            while (hrtime(true) < $finishAt) {
                // Deterministic monotonic work without sleeping.
            }

            return new Response;
        });
    }

    public function test_matched_route_logs_the_route_name_and_uri_template(): void
    {
        $channel = Mockery::mock(LoggerInterface::class);
        Log::shouldReceive('channel')->with('application')->andReturn($channel);
        $channel->shouldReceive('log')
            ->once()
            ->withArgs(static fn (string $level, string $message, array $context): bool => $message === 'http.request.completed'
                && $context['route'] === 'observability.fixture'
                && $context['path'] === 'api/v1/fixtures/{fixture}');

        $request = Request::create('/api/v1/fixtures/123?token=must-not-be-logged');
        $route = (new Route('GET', 'api/v1/fixtures/{fixture}', static fn (): null => null))
            ->name('observability.fixture');
        $request->setRouteResolver(static fn (): Route => $route);

        app(LogApiRequests::class)->handle($request, static fn (): Response => new Response);
    }

    public function test_unresolved_route_logs_a_query_free_safe_path_without_throwing(): void
    {
        $channel = Mockery::mock(LoggerInterface::class);
        Log::shouldReceive('channel')->with('application')->andReturn($channel);
        $channel->shouldReceive('log')
            ->once()
            ->withArgs(function (string $level, string $message, array $context): bool {
                $this->assertSame('http.request.completed', $message);
                $this->assertNull($context['route']);
                $this->assertSame('unmatched/path', $context['path']);
                $this->assertStringNotContainsString('private-value', json_encode($context, JSON_THROW_ON_ERROR));

                return true;
            });

        $request = Request::create('/unmatched/path?token=private-value');
        $request->setRouteResolver(static fn (): string => 'not-a-route');

        $response = app(LogApiRequests::class)->handle(
            $request,
            static fn (): Response => new Response('unchanged', 418),
        );

        $this->assertSame(418, $response->getStatusCode());
        $this->assertSame('unchanged', $response->getContent());
    }

    public function test_thrown_request_emits_one_safe_failure_event_and_rethrows_the_same_exception(): void
    {
        $requestId = (string) Str::uuid();
        $exception = new RuntimeException('secret exception message');
        $request = Request::create('/unmatched/failure?token=private-query', 'POST', [
            'password' => 'private-body',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer private-credential',
        ]);
        $request->attributes->set('request_id', $requestId);
        app()->instance('request_id', $requestId);
        app()->instance('request', $request);

        $channel = Mockery::mock(LoggerInterface::class);
        Log::shouldReceive('channel')->with('application')->andReturn($channel);
        $channel->shouldReceive('log')
            ->once()
            ->withArgs(function (string $level, string $message, array $context) use ($requestId): bool {
                $encoded = json_encode($context, JSON_THROW_ON_ERROR);

                $this->assertSame('error', $level);
                $this->assertSame('http.request.failed', $message);
                $this->assertSame($requestId, $context['request_id']);
                $this->assertSame('POST', $context['method']);
                $this->assertNull($context['route']);
                $this->assertSame('unmatched/failure', $context['path']);
                $this->assertSame('failed', $context['outcome']);
                $this->assertSame(RuntimeException::class, $context['exception_class']);
                $this->assertIsFloat($context['duration_ms']);
                $this->assertStringNotContainsString('secret exception message', $encoded);
                $this->assertStringNotContainsString('private-body', $encoded);
                $this->assertStringNotContainsString('private-credential', $encoded);
                $this->assertStringNotContainsString('private-query', $encoded);

                return true;
            });

        try {
            app(LogApiRequests::class)->handle($request, static fn () => throw $exception);
            $this->fail('The downstream exception was not rethrown.');
        } catch (RuntimeException $thrown) {
            $this->assertSame($exception, $thrown);
        }
    }

    public function test_logging_failure_does_not_replace_the_original_exception(): void
    {
        $original = new RuntimeException('original');
        Log::shouldReceive('channel')
            ->once()
            ->with('application')
            ->andThrow(new RuntimeException('logger unavailable'));

        try {
            app(LogApiRequests::class)->handle(
                Request::create('/failure'),
                static fn () => throw $original,
            );
            $this->fail('The downstream exception was not rethrown.');
        } catch (RuntimeException $thrown) {
            $this->assertSame($original, $thrown);
        }
    }

    public function test_a_slow_query_logs_only_a_signature_and_safe_metadata(): void
    {
        $channel = Mockery::mock(LoggerInterface::class);
        Log::shouldReceive('channel')->with('system')->andReturn($channel);
        $channel->shouldReceive('log')
            ->once()
            ->withArgs(function (string $level, string $message, array $context): bool {
                $this->assertSame('warning', $level);
                $this->assertSame('database.query.slow', $message);
                $this->assertSame(1000.0, $context['duration_ms']);
                $this->assertSame('testing', $context['connection']);
                $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $context['query_signature']);
                $this->assertArrayNotHasKey('sql', $context);
                $this->assertArrayNotHasKey('bindings', $context);

                return true;
            });

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getName')->once()->andReturn('testing');
        $connection->shouldReceive('prepareBindings')->once()->andReturn([]);

        Event::dispatch(new QueryExecuted(
            'select * from users where email = ? and password = ?',
            ['private@example.test', 'must-not-be-logged'],
            1000.0,
            $connection,
        ));
    }
}
