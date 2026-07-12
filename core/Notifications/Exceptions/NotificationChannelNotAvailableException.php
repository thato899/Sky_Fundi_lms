<?php

declare(strict_types=1);

namespace Core\Notifications\Exceptions;

use Exception;

/**
 * Thrown by a placeholder channel (SMS, WhatsApp, Push — see
 * Infrastructure/Channels) if one is ever actually selected. Mirrors
 * Core\AIGateway's placeholder-provider pattern: these channels are
 * real, registered, and reachable via NotificationService::send(),
 * they just don't have a live transport wired up yet.
 */
final class NotificationChannelNotAvailableException extends Exception
{
    public static function forChannel(string $channel): self
    {
        return new self("The \"{$channel}\" notification channel is not implemented yet. See core/Notifications/README.md.");
    }
}
