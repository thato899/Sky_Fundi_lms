<?php

declare(strict_types=1);

namespace Core\Mail\Infrastructure\Providers;

/**
 * Placeholder — see AbstractPlaceholderMailProvider. Implementing
 * this fully means OAuth2 (service account or domain-wide delegation)
 * against the Gmail API's users.messages.send endpoint.
 */
final class GoogleWorkspaceMailProvider extends AbstractPlaceholderMailProvider
{
    public function name(): string
    {
        return 'google_workspace';
    }
}
