<?php

declare(strict_types=1);

namespace Core\Support\Contracts;

/**
 * Marker interface for domain events that should be automatically
 * recorded to the audit trail when dispatched, without the firing
 * service needing to also call AuditLogService::record() by hand.
 * See Core\AuditLogs\Listeners\AuditableEventSubscriber.
 *
 * Existing Core services (Auth, Users, RBAC, Settings, Branding,
 * Modules) already call AuditLogService::record() directly at the
 * point of action and do NOT implement this interface on their
 * events — changing that would touch already-shipped Core behaviour
 * for no benefit. This interface exists for NEW events (this
 * prompt's Licensing, Subscriptions, Security Centre, Feature Flags,
 * ...) so they get audited automatically instead of duplicating the
 * same record() call in every new service.
 */
interface Auditable
{
    /**
     * The audit action string, e.g. "license.activated".
     */
    public function auditAction(): string;

    /**
     * The Eloquent model this event's action was performed against,
     * or null if there isn't a single clear target.
     */
    public function auditTarget(): ?\Illuminate\Database\Eloquent\Model;

    /**
     * Structured before/after state for the audit record. Either key
     * may be omitted or null.
     *
     * @return array{before?: array|null, after?: array|null}
     */
    public function auditContext(): array;
}
