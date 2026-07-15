<?php

declare(strict_types=1);

namespace Modules\Assessments\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Assessments\Infrastructure\Models\AssessmentCategory;
use Modules\Organizations\Infrastructure\Models\Organization;

final class AssessmentCategoryService
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function create(Organization $organization, User $actor, array $data): AssessmentCategory
    {
        $this->unique((string) $organization->getKey(), $data);
        $category = AssessmentCategory::query()->create([...Arr::only($data, ['name', 'code', 'description', 'default_weighting', 'display_order']), 'organization_id' => $organization->getKey(), 'is_active' => true, 'created_by' => $actor->getKey(), 'updated_by' => $actor->getKey()]);
        $this->audit->record('assessment.category_created', $category, after: ['organization_id' => $organization->getKey(), 'active' => true]);

        return $category;
    }

    public function update(AssessmentCategory $category, User $actor, array $data): AssessmentCategory
    {
        $this->unique((string) $category->getAttribute('organization_id'), $data, (string) $category->getKey());
        $category->fill(Arr::only($data, ['name', 'code', 'description', 'default_weighting', 'display_order']))->setAttribute('updated_by', $actor->getKey())->save();
        $this->audit->record('assessment.category_updated', $category, after: ['organization_id' => $category->getAttribute('organization_id')]);

        return $category->refresh();
    }

    public function active(AssessmentCategory $category, User $actor, bool $active): AssessmentCategory
    {
        $category->update(['is_active' => $active, 'updated_by' => $actor->getKey()]);
        $this->audit->record($active ? 'assessment.category_activated' : 'assessment.category_deactivated', $category, after: ['organization_id' => $category->getAttribute('organization_id'), 'active' => $active]);

        return $category->refresh();
    }

    public function reorder(Organization $organization, User $actor, array $ids): void
    {
        DB::transaction(function () use ($organization, $actor, $ids): void {
            /** @var Collection<string, AssessmentCategory> $categories */
            $categories = AssessmentCategory::query()->where('organization_id', $organization->getKey())->whereIn('uuid', $ids)->lockForUpdate()->get()->keyBy('uuid');
            if ($categories->count() !== count(array_unique($ids))) {
                throw new DomainException('Every category must belong to the active organization.');
            }
            foreach ($ids as $order => $uuid) {
                $category = $categories->get($uuid);
                $category?->update(['display_order' => $order + 1, 'updated_by' => $actor->getKey()]);
            }
            $this->audit->record('assessment.categories_reordered', after: ['organization_id' => $organization->getKey(), 'category_count' => count($ids)]);
        }, 3);
    }

    private function unique(string $organizationId, array $data, ?string $except = null): void
    {
        foreach (['name', 'code'] as $field) {
            if (! isset($data[$field]) || trim((string) $data[$field]) === '') {
                continue;
            }
            $exists = AssessmentCategory::query()->where('organization_id', $organizationId)->where($field, trim((string) $data[$field]))->when($except, fn ($q) => $q->whereKeyNot($except))->exists();
            if ($exists) {
                throw new DomainException("The category {$field} is already in use in this organization.");
            }
        }
    }
}
