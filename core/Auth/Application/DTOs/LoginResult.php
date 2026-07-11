<?php

declare(strict_types=1);

namespace Core\Auth\Application\DTOs;

use Core\Users\Infrastructure\Models\User;

final readonly class LoginResult
{
    public function __construct(
        public User $user,
        public string $token,
        public string $tokenType = 'Bearer',
    ) {}
}
