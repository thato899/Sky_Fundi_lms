<?php

declare(strict_types=1);

namespace Core\Notifications\Infrastructure\Channels;

use Core\Notifications\Exceptions\NotificationChannelNotAvailableException;
use Illuminate\Notifications\Notification;

/**
 * Placeholder — see core/Notifications/README.md. Implementing this
 * fully means calling the WhatsApp Business API from send().
 */
final class WhatsAppChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        throw NotificationChannelNotAvailableException::forChannel('whatsapp');
    }
}
