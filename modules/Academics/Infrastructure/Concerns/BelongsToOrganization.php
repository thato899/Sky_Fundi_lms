<?php

declare(strict_types=1);

namespace Modules\Academics\Infrastructure\Concerns;

use Core\Support\Exceptions\DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Organizations\Infrastructure\Models\Organization;

trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $query): void {
            $organization = request()->attributes->get('organization');
            if ($organization instanceof Organization) {
                $query->where($query->qualifyColumn('organization_id'), $organization->getKey());
            }
        });

        static::saving(function ($model): void {
            $organization = request()->attributes->get('organization');
            if ($organization instanceof Organization) {
                if ($model->getAttribute('organization_id') !== null && $model->getAttribute('organization_id') !== $organization->getKey()) {
                    throw new DomainException('Academic ownership must come from the active organization context.');
                }
                $model->setAttribute('organization_id', $organization->getKey());
            }

            if ($model->getAttribute('organization_id') === null) {
                throw new DomainException('An organization is required for academic records.');
            }

            foreach ($model->organizationReferences() as $column => $related) {
                $id = $model->getAttribute($column);
                if ($id !== null && ! $related::query()->withoutGlobalScopes()->whereKey($id)->where('organization_id', $model->getAttribute('organization_id'))->exists()) {
                    throw new DomainException('Related academic records must belong to the same organization.');
                }
            }

            if ($model instanceof ClassGroup) {
                $grade = Grade::query()->withoutGlobalScopes()->find($model->getAttribute('grade_id'));
                if ($grade !== null && $grade->getAttribute('academic_year_id') !== null && $grade->getAttribute('academic_year_id') !== $model->getAttribute('academic_year_id')) {
                    throw new DomainException('The class academic year must be compatible with its grade.');
                }
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeForOrganization(Builder $query, Organization|string $organization): Builder
    {
        return $query->withoutGlobalScope('organization')->where(
            $query->qualifyColumn('organization_id'),
            $organization instanceof Organization ? $organization->getKey() : $organization,
        );
    }

    protected function organizationReferences(): array
    {
        return [];
    }
}
