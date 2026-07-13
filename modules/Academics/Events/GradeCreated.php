<?php

declare(strict_types=1);

namespace Modules\Academics\Events;

use Core\Support\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Academics\Infrastructure\Models\Grade;

final class GradeCreated implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Grade $grade,
    ) {}

    public function auditAction(): string
    {
        return 'academics.grade.created';
    }

    public function auditTarget(): ?Model
    {
        return $this->grade;
    }

    public function auditContext(): array
    {
        return ['after' => ['name' => $this->grade->name, 'order' => $this->grade->order]];
    }
}
