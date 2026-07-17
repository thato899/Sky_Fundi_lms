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
use Mockery;
use Psr\Log\LoggerInterface;
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
