<?php

declare(strict_types=1);

namespace Core\Mail\Contracts;

use Core\Support\Contracts\ProviderInterface;

/**
 * A mail provider maps to one of Laravel's own configured mailers
 * (config/mail.php "mailers"), rather than reimplementing message
 * transport itself — Core\Mail adds provider selection and
 * availability reporting on top of Laravel Mail, it does not replace
 * it. Callers keep using standard Laravel Mailable/Notification
 * classes via Core\Mail\Application\MailManager::mailer(). See
 * core/Mail/README.md.
 */
interface MailProviderInterface extends ProviderInterface
{
    /**
     * The config/mail.php "mailers" key this provider resolves to.
     *
     * @throws \Core\Support\Exceptions\ProviderNotAvailableException for a placeholder provider
     */
    public function mailerName(): string;
}
