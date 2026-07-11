<?php

declare(strict_types=1);

namespace Core\Auth\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Auth\Application\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LogoutController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AuthService $auth,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return $this->message('Logged out.');
    }
}
