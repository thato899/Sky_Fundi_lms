<?php

declare(strict_types=1);

namespace Core\Auth\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Auth\Http\Requests\ForgotPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

/**
 * Uses Laravel's built-in password broker (see config/auth.php
 * "passwords" and User::CanResetPassword) rather than a bespoke token
 * system — the platform's own value-add here is auditing and account
 * status checks, not reinventing token generation.
 */
final class ForgotPasswordController extends Controller
{
    use ApiResponse;

    public function store(ForgotPasswordRequest $request): JsonResponse
    {
        // Always return a generic success message regardless of whether
        // the email exists, to avoid leaking account existence — see
        // docs/security/policies.md.
        Password::broker('users')->sendResetLink($request->only('email'));

        return $this->message('If an account exists for that email, a password reset link has been sent.');
    }
}
