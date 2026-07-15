<?php

declare(strict_types=1);

namespace Core\Api\Exceptions;

use Core\Auth\Exceptions\AccountNotActiveException;
use Core\Support\Exceptions\DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Registers the platform's global exception -> JSON response mapping,
 * per docs/api/error-handling.md. Wired from bootstrap/app.php so every
 * Core service and future module gets consistent error shapes without
 * each one building its own try/catch.
 */
final class ApiExceptionHandler
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! self::shouldRender($request)) {
                return null;
            }

            return self::error(
                code: 'validation_failed',
                message: 'The given data was invalid.',
                status: 422,
                details: $e->errors(),
                validationErrors: $e->errors(),
            );
        });

        $exceptions->render(function (AccountNotActiveException $e, Request $request) {
            if (! self::shouldRender($request)) {
                return null;
            }

            return self::error(
                code: 'account_not_active',
                message: $e->getMessage(),
                status: 403,
            );
        });

        $exceptions->render(function (DomainException $e, Request $request) {
            if (! self::shouldRender($request)) {
                return null;
            }

            return self::error(
                code: 'domain_rule_violation',
                message: $e->getMessage(),
                status: $e->httpStatus(),
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! self::shouldRender($request)) {
                return null;
            }

            return self::error(
                code: 'unauthenticated',
                message: 'Authentication is required to access this resource.',
                status: 401,
            );
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! self::shouldRender($request)) {
                return null;
            }

            return self::error(
                code: 'forbidden',
                message: $e->getMessage() ?: 'You do not have permission to perform this action.',
                status: 403,
            );
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if (! self::shouldRender($request)) {
                return null;
            }

            return self::error(
                code: 'not_found',
                message: 'The requested resource could not be found.',
                status: 404,
            );
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! self::shouldRender($request)) {
                return null;
            }

            return self::error(
                code: 'not_found',
                message: 'The requested endpoint does not exist.',
                status: 404,
            );
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! self::shouldRender($request)) {
                return null;
            }

            return self::error(
                code: 'http_error',
                message: $e->getMessage() ?: 'An error occurred processing your request.',
                status: $e->getStatusCode(),
            );
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! self::shouldRender($request)) {
                return null;
            }

            $status = 500;

            report($e);

            return self::error(
                code: 'server_error',
                message: config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.',
                status: $status,
                details: config('app.debug') ? ['exception' => $e::class, 'trace' => $e->getTraceAsString()] : null,
            );
        });
    }

    private static function shouldRender(Request $request): bool
    {
        return $request->expectsJson() || $request->is('api/*');
    }

    private static function error(
        string $code,
        string $message,
        int $status,
        ?array $details = null,
        ?array $validationErrors = null,
    ): JsonResponse {
        $payload = [
            'error' => array_filter([
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ], fn ($v) => $v !== null),
        ];

        if ($validationErrors !== null) {
            // Preserve Laravel's conventional top-level validation shape
            // while retaining the platform's structured error envelope.
            $payload['errors'] = $validationErrors;
        }

        return response()->json($payload, $status)
            ->header('X-Request-Id', request()->header('X-Request-Id', (string) Str::uuid()));
    }
}
