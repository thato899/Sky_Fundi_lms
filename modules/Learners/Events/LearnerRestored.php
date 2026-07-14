<?php

declare(strict_types=1);

namespace Modules\Learners\Events;

use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Learners\Domain\Enums\LearnerStatus;
use Modules\Learners\Infrastructure\Models\LearnerProfile;

final class LearnerRestored
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly LearnerProfile $learner,
        public readonly LearnerStatus $restoredStatus,
        public readonly User $actor,
        public readonly ?string $reason,
    ) {}
}
