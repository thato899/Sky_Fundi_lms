<?php

declare(strict_types=1);

namespace Modules\Academics\Events;

use Core\Support\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Academics\Infrastructure\Models\AcademicYear;

final class AcademicYearCreated implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly AcademicYear $academicYear,
    ) {}

    public function auditAction(): string
    {
        return 'academics.academic_year.created';
    }

    public function auditTarget(): ?Model
    {
        return $this->academicYear;
    }

    public function auditContext(): array
    {
        return ['after' => ['name' => $this->academicYear->name]];
    }
}
