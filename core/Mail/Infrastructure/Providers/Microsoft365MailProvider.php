<?php

declare(strict_types=1);

namespace Core\Mail\Infrastructure\Providers;

/**
 * Placeholder — see AbstractPlaceholderMailProvider. Implementing
 * this fully means OAuth2 client-credentials auth against Microsoft
 * Graph's /sendMail endpoint, since Microsoft 365 does not use static
 * SMTP credentials the way the other providers here do.
 */
final class Microsoft365MailProvider extends AbstractPlaceholderMailProvider
{
    public function name(): string
    {
        return 'microsoft365';
    }
}
