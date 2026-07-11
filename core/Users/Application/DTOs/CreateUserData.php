<?php

declare(strict_types=1);

namespace Core\Users\Application\DTOs;

/**
 * Data required to create a platform user, decoupled from the HTTP
 * request shape so UserService can be called from a controller, a
 * console command, or a queued import job identically.
 */
final readonly class CreateUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $timezone = 'UTC',
        public string $locale = 'en',
    ) {}
}
