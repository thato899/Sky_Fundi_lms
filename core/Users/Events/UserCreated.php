<?php

declare(strict_types=1);

namespace Core\Users\Events;

use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a new platform user is persisted. Future modules subscribe
 * to this rather than being called synchronously by UserService, per
 * docs/architecture/module-system.md#cross-module-communication.
 */
final class UserCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
    ) {}
}
