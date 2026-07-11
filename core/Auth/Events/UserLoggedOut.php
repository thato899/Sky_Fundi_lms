<?php

declare(strict_types=1);

namespace Core\Auth\Events;

use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class UserLoggedOut
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
    ) {}
}
