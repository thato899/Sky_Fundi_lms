<?php

declare(strict_types=1);

namespace Core\Mail\Infrastructure\Providers;

use Core\Mail\Contracts\MailProviderInterface;

/**
 * Wraps Laravel's native "ses" mailer transport. Requires the
 * "aws/aws-sdk-php" package (declared in composer.json) and AWS_*
 * credentials — see docs/environment-variables.md.
 */
final class SesMailProvider implements MailProviderInterface
{
    public function name(): string
    {
        return 'ses';
    }

    public function isAvailable(): bool
    {
        return filled(config('services.ses.key')) && filled(config('services.ses.secret'));
    }

    public function mailerName(): string
    {
        return 'ses';
    }
}
