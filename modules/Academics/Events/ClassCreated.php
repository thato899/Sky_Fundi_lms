<?php

declare(strict_types=1);

namespace Modules\Academics\Events;

use Core\Support\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Academics\Infrastructure\Models\ClassGroup;

final class ClassCreated implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ClassGroup $class,
    ) {}

    public function auditAction(): string
    {
        return 'academics.class.created';
    }

    public function auditTarget(): ?Model
    {
        return $this->class;
    }

    public function auditContext(): array
    {
        return ['after' => ['name' => $this->class->name, 'grade_id' => $this->class->grade_id]];
    }
}
