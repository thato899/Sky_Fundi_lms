<?php

declare(strict_types=1);

namespace Core\Auth\Domain\Enums;

/**
 * What a successfully-password-authenticated request must still do
 * before AuthService::issueToken() / Auth::login() actually completes
 * the session — see Core\Auth\Application\TwoFactorLoginService.
 */
enum TwoFactorStatus: string
{
    /** No two-factor step needed; login completes immediately. */
    case NotRequired = 'not_required';

    /** The user has two-factor enabled; must submit a code or recovery code. */
    case ChallengeRequired = 'challenge_required';

    /** The user's organization enforces two-factor but they haven't
     *  enrolled yet; must complete enrollment before login completes. */
    case SetupRequired = 'setup_required';
}
