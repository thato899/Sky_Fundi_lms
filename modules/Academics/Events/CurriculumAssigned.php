<?php

declare(strict_types=1);

namespace Modules\Academics\Events;

use Core\Support\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Academics\Infrastructure\Models\Curriculum;

/**
 * Fired when a Curriculum is assigned to a Grade or Subject (see
 * Application\GradeService::assignCurriculum() and
 * Application\SubjectService::assignCurriculum()) — not when a
 * Curriculum itself is created.
 */
final class CurriculumAssigned implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Curriculum $curriculum,
        public readonly Model $target,
    ) {}

    public function auditAction(): string
    {
        return 'academics.curriculum.assigned';
    }

    public function auditTarget(): ?Model
    {
        return $this->target;
    }

    public function auditContext(): array
    {
        return ['after' => ['curriculum_id' => $this->curriculum->id, 'curriculum_code' => $this->curriculum->code]];
    }
}
