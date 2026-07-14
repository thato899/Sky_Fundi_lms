<?php

declare(strict_types=1);

namespace Modules\Learners\Events;

use Core\Support\Contracts\Auditable;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Learners\Domain\Enums\LearnerStatus;
use Modules\Learners\Infrastructure\Models\LearnerProfile;

final class LearnerRestored implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly LearnerProfile $learner,
        public readonly LearnerStatus $restoredStatus,
        public readonly User $actor,
        public readonly ?string $reason,
    ) {}

    public function auditAction(): string
    {
        return 'learners.restored';
    }

    public function auditTarget(): ?Model
    {
        return $this->learner;
    }

    public function auditContext(): array
    {
        return ['before' => ['status' => 'archived'], 'after' => ['status' => $this->restoredStatus->value, 'reason' => $this->reason]];
    }
}
