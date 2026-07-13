<?php

declare(strict_types=1);

namespace Modules\Academics\Events;

use Core\Support\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Academics\Infrastructure\Models\AcademicTerm;

final class AcademicTermCreated implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly AcademicTerm $academicTerm,
    ) {}

    public function auditAction(): string
    {
        return 'academics.academic_term.created';
    }

    public function auditTarget(): ?Model
    {
        return $this->academicTerm;
    }

    public function auditContext(): array
    {
        return ['after' => ['name' => $this->academicTerm->name, 'term_number' => $this->academicTerm->term_number]];
    }
}
