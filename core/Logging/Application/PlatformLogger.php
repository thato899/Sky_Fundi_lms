<?php

declare(strict_types=1);

namespace Core\Logging\Application;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

/**
 * Thin wrapper around Laravel's channel-based logging (see
 * config/logging.php) that guarantees every log line written through it
 * carries structured context — correlation id, actor, tenant — so logs
 * stay traceable across a modular, multi-tenant platform without every
 * Core service or module reinventing this. See
 * docs/development/README.md#logging-strategy.
 *
 * Core services and modules should depend on this class (or its
 * per-concern convenience methods) rather than calling the `Log` facade
 * directly, so the mandatory context is never accidentally skipped.
 */
final class PlatformLogger
{
    public function application(string $level, string $message, array $context = []): void
    {
        $this->write('application', $level, $message, $context);
    }

    public function ai(string $level, string $message, array $context = []): void
    {
        $this->write('ai', $level, $message, $context);
    }

    public function security(string $level, string $message, array $context = []): void
    {
        $this->write('security', $level, $message, $context);
    }

    public function authentication(string $level, string $message, array $context = []): void
    {
        $this->write('authentication', $level, $message, $context);
    }

    public function system(string $level, string $message, array $context = []): void
    {
        $this->write('system', $level, $message, $context);
    }

    public function channel(string $channel): LoggerInterface
    {
        return Log::channel($channel);
    }

    private function write(string $channel, string $level, string $message, array $context): void
    {
        Log::channel($channel)->log($level, $message, array_merge($context, $this->baseContext()));
    }

    /**
     * Context every log line carries regardless of caller, so any log
     * entry can be traced back to the request/user that produced it.
     * Tenant and membership identifiers come only from trusted request
     * attributes populated by organization-context middleware.
     */
    private function baseContext(): array
    {
        $request = function_exists('request') ? request() : null;

        return array_filter([
            'request_id' => app()->bound('request_id') ? app('request_id') : null,
            'actor_id' => Auth::id(),
            'membership_id' => $request?->attributes->get('organization_membership')?->getKey(),
            'organization_id' => $request?->attributes->get('organization')?->getKey(),
        ], static fn ($value) => $value !== null);
    }
}
