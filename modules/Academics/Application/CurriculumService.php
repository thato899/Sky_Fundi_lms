<?php

declare(strict_types=1);

namespace Modules\Academics\Application;

use Core\AuditLogs\Application\AuditLogService;
use Illuminate\Support\Collection;
use Modules\Academics\Infrastructure\Models\Curriculum;

/**
 * Curricula are database-driven, never hardcoded — see
 * modules/Academics/README.md#curriculum. CAPS/IEB/Cambridge are seed
 * data (see database/seeders/CurriculumSeeder.php), not special-cased
 * in code; a Custom curriculum is just another row.
 */
final class CurriculumService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function create(array $attributes): Curriculum
    {
        $curriculum = Curriculum::create($attributes);

        $this->auditLog->record(action: 'academics.curriculum.created', target: $curriculum, after: $attributes);

        return $curriculum;
    }

    public function update(Curriculum $curriculum, array $attributes): Curriculum
    {
        $before = $curriculum->only(array_keys($attributes));
        $curriculum->update($attributes);

        $this->auditLog->record(action: 'academics.curriculum.updated', target: $curriculum, before: $before, after: $attributes);

        return $curriculum->fresh();
    }

    public function deactivate(Curriculum $curriculum): Curriculum
    {
        return $this->update($curriculum, ['is_active' => false]);
    }

    public function active(): Collection
    {
        return Curriculum::query()->where('is_active', true)->orderBy('name')->get();
    }
}
