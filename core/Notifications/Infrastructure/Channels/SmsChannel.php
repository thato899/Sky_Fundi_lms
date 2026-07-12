<?php

declare(strict_types=1);

namespace Core\Notifications\Infrastructure\Channels;

use Core\Notifications\Exceptions\NotificationChannelNotAvailableException;
use Illuminate\Notifications\Notification;

/**
 * Placeholder — see core/Notifications/README.md. Implementing this
 * fully means calling an SMS gateway (e.g. Twilio, Africa's Talking)
 * from send() and returning; the Notification class itself is
 * already channel-agnostic (CoreNotification), so no other code
 * changes when this is implemented.
 */
final class SmsChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        throw NotificationChannelNotAvailableException::forChannel('sms');
    }
}
