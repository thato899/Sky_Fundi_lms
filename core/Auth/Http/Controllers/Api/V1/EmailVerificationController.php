<?php

declare(strict_types=1);

namespace Core\Auth\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\AuditLogs\Application\AuditLogService;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EmailVerificationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function verify(EmailVerificationRequest $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->message('Email already verified.');
        }

        $request->fulfill();

        $this->auditLog->record(action: 'auth.email_verified', target: $request->user());

        return $this->message('Email verified.');
    }

    public function resend(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->message('Email already verified.');
        }

        $request->user()->sendEmailVerificationNotification();

        return $this->message('Verification email sent.');
    }
}
