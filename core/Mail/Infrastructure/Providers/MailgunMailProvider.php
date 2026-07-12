<?php

declare(strict_types=1);

namespace Core\Mail\Infrastructure\Providers;

use Core\Mail\Contracts\MailProviderInterface;

/**
 * Wraps Laravel's native "mailgun" mailer transport. Requires the
 * "symfony/mailgun-mailer" package (declared in composer.json) and
 * MAILGUN_* credentials — see docs/environment-variables.md.
 */
final class MailgunMailProvider implements MailProviderInterface
{
    public function name(): string
    {
        return 'mailgun';
    }

    public function isAvailable(): bool
    {
        return filled(config('services.mailgun.secret')) && filled(config('services.mailgun.domain'));
    }

    public function mailerName(): string
    {
        return 'mailgun';
    }
}
