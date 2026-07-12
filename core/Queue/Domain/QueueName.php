<?php

declare(strict_types=1);

namespace Core\Queue\Domain;

/**
 * The platform's named queues, per core/Queue/README.md. Every job
 * and queued notification/mailable is dispatched onto one of these
 * rather than the default queue, so a future deployment can size and
 * prioritise workers per concern (e.g. more workers on `ai`, fewer on
 * `backups`) without every dispatch site hardcoding a queue string
 * that could typo or drift.
 */
enum QueueName: string
{
    case Default = 'default';
    case Ai = 'ai';
    case Reports = 'reports';
    case Imports = 'imports';
    case Exports = 'exports';
    case Notifications = 'notifications';
    case Email = 'email';
    case Backups = 'backups';
}
