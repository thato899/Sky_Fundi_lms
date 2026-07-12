<?php

declare(strict_types=1);

namespace Core\Mail\Infrastructure\Providers;

use Core\Mail\Contracts\MailProviderInterface;

final class SmtpMailProvider implements MailProviderInterface
{
    public function name(): string
    {
        return 'smtp';
    }

    public function isAvailable(): bool
    {
        return filled(config('mail.mailers.smtp.host'));
    }

    public function mailerName(): string
    {
        return 'smtp';
    }
}
