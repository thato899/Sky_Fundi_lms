<?php

declare(strict_types=1);

namespace Modules\Assessments\Policies;

use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Users\Infrastructure\Models\User;
use Modules\Assessments\Infrastructure\Models\AssessmentCategory;

final class AssessmentCategoryPolicy
{
    public function __construct(private readonly PermissionResolver $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user);
    }

    public function manageCategories(User $user, ?AssessmentCategory $category = null): bool
    {
        return $this->allows($user, $category);
    }

    private function allows(User $user, ?AssessmentCategory $category = null): bool
    {
        $membership = request()->attributes->get('organization_membership');

        return $membership instanceof Membership && $membership->getAttribute('user_id') === $user->getKey() && (! $category || $category->getAttribute('organization_id') === $membership->getAttribute('organization_id')) && $this->permissions->allows($membership, 'assessment_categories.manage');
    }
}
