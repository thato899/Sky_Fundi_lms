<?php

declare(strict_types=1);

namespace Core\Notifications\Infrastructure\Channels;

use Core\Notifications\Exceptions\NotificationChannelNotAvailableException;
use Illuminate\Notifications\Notification;

/**
 * Placeholder — see core/Notifications/README.md and
 * docs/mobile/README.md ("Push Notifications... provider-agnostic").
 * Implementing this fully means calling FCM/APNs from send().
 */
final class PushChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        throw NotificationChannelNotAvailableException::forChannel('push');
    }
}
