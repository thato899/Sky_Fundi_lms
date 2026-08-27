<?php

declare(strict_types=1);

namespace Core\Auth\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Auth\Application\TwoFactorAuthenticationService;
use Core\Auth\Http\Requests\ConfirmTwoFactorRequest;
use Core\Auth\Http\Requests\DisableTwoFactorRequest;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Self-service enrollment for the authenticated user's own account.
 * Never acts on any user other than $request->user() — there is no
 * "manage another user's two-factor" surface, by design.
 */
final class TwoFactorController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TwoFactorAuthenticationService $twoFactor) {}

    public function show(Request $request): JsonResponse
    {
        return $this->ok(['enabled' => $this->user($request)->hasTwoFactorEnabled()]);
    }

    /** Starts (or restarts) enrollment; does not enable 2FA until confirm(). */
    public function store(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $secret = $this->twoFactor->generateSecret($user);

        return $this->ok([
            'secret' => $secret,
            'provisioning_uri' => $this->twoFactor->provisioningUri($user),
        ]);
    }

    public function confirm(ConfirmTwoFactorRequest $request): JsonResponse
    {
        try {
            $codes = $this->twoFactor->confirm($this->user($request), $request->string('code')->value());
        } catch (DomainException $exception) {
            return $this->message($exception->getMessage(), 422);
        }

        return $this->ok(['recovery_codes' => $codes]);
    }

    public function destroy(DisableTwoFactorRequest $request): JsonResponse
    {
        $user = $this->user($request);
        if (! Hash::check($request->string('password')->value(), (string) $user->getAuthPassword())) {
            return $this->message('The provided password is incorrect.', 422);
        }

        $this->twoFactor->disable($user);

        return $this->noContent();
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        try {
            $codes = $this->twoFactor->regenerateRecoveryCodes($this->user($request));
        } catch (DomainException $exception) {
            return $this->message($exception->getMessage(), 422);
        }

        return $this->ok(['recovery_codes' => $codes]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
