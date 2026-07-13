<?php

declare(strict_types=1);

namespace Modules\Academics\Events;

use Core\Support\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Academics\Infrastructure\Models\Subject;

final class SubjectCreated implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Subject $subject,
    ) {}

    public function auditAction(): string
    {
        return 'academics.subject.created';
    }

    public function auditTarget(): ?Model
    {
        return $this->subject;
    }

    public function auditContext(): array
    {
        return ['after' => ['code' => $this->subject->code, 'name' => $this->subject->name]];
    }
}
