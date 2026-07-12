<?php

declare(strict_types=1);

namespace Core\AuditLogs\Listeners;

use Core\AuditLogs\Application\AuditLogService;
use Core\Support\Contracts\Auditable;
use Illuminate\Events\Dispatcher;

/**
 * Automatically records any dispatched event implementing
 * Core\Support\Contracts\Auditable, so new services (Licensing,
 * Subscriptions, Deployment, and everything added in this
 * infrastructure layer) get an audit trail for free by implementing
 * that interface on their events, instead of every service hand-calling
 * AuditLogService::record() at each transition. See that interface's
 * docblock for why existing Core services (Auth, Users, RBAC, Settings,
 * Branding, Modules) are intentionally NOT migrated to this pattern.
 *
 * Registered via Event::subscribe() in AuditLogsServiceProvider using a
 * wildcard listener — Laravel has no built-in "listen for any event
 * implementing this interface" primitive, so this is the standard way
 * to achieve it without a static list of every Auditable event class.
 */
final class AuditableEventSubscriber
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function handle(string $eventName, array $data): void
    {
        $event = $data[0] ?? null;

        if (! $event instanceof Auditable) {
            return;
        }

        $context = $event->auditContext();

        $this->auditLog->record(
            action: $event->auditAction(),
            target: $event->auditTarget(),
            before: $context['before'] ?? null,
            after: $context['after'] ?? null,
        );
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen('*', [self::class, 'handle']);
    }
}
