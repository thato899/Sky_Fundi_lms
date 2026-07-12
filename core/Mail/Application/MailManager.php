<?php

declare(strict_types=1);

namespace Core\Mail\Application;

use Core\Support\Exceptions\ProviderNotAvailableException;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

/**
 * The entry point every Core service and future module uses to send
 * mail — resolves the platform's configured mail *provider* (see
 * config('mail_providers.default_provider')) to a real Laravel Mailer
 * instance. Callers still send standard Laravel Mailable/Notification
 * classes through the returned Mailer — Core\Mail adds provider
 * selection and availability checking on top of Laravel Mail, it does
 * not replace Mailable/Notification authoring. See core/Mail/README.md.
 */
final class MailManager
{
    public function __construct(
        private readonly MailProviderFactory $factory,
    ) {}

    public function mailer(?string $providerName = null): Mailer
    {
        $providerName ??= (string) config('mail_providers.default_provider');
        $provider = $this->factory->make($providerName);

        if (! $provider->isAvailable()) {
            throw ProviderNotAvailableException::forProvider('mail', $providerName);
        }

        return Mail::mailer($provider->mailerName());
    }
}
