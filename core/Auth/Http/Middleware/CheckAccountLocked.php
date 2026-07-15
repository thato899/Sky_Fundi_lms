<?php

declare(strict_types=1);

namespace Core\Auth\Http\Middleware;

use Closure;
use Core\Users\Domain\Enums\UserStatus;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Guards against a token issued before an account was locked/suspended
 * still working afterwards. Applied on top of `auth:sanctum` for
 * sensitive routes — see docs/security/policies.md#session-policy.
 * (AuthService::revokeAllTokens proactively revokes tokens on lock/
 * suspend, so in the common case this middleware never triggers — it's
 * defence in depth for any token issued in a race with the status
 * change.)
 */
final class CheckAccountLocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isLocked()) {
            throw new HttpException(423, 'This account is locked. Contact a platform administrator.');
        }

        if ($user !== null && ! in_array($user->status, [UserStatus::Active, UserStatus::PendingVerification], true)) {
            throw new HttpException(403, 'This account is not available. Contact a platform administrator.');
        }

        return $next($request);
    }
}
