<?php

declare(strict_types=1);

namespace Modules\Staff\Infrastructure\Models;

use Core\Identity\Infrastructure\Models\Membership;
use Core\Support\Traits\HasUuidPrimaryKey;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Academics\Infrastructure\Models\Department;
use Modules\Organizations\Infrastructure\Models\Organization;

/** @property string $user_id */
final class StaffProfile extends Model
{
    use HasUuidPrimaryKey,SoftDeletes;

    protected $fillable = ['organization_id', 'organization_membership_id', 'user_id', 'employee_number', 'title', 'first_name', 'last_name', 'staff_type', 'job_title', 'employment_type', 'employment_status', 'department_id', 'reports_to_staff_id', 'hire_date', 'contract_end_date', 'qualification_summary', 'specializations', 'languages', 'weekly_working_hours', 'availability_status', 'work_email', 'work_phone', 'onboarding_status', 'notes', 'portal_access_enabled', 'archived_at'];

    protected function casts(): array
    {
        return ['specializations' => 'array', 'languages' => 'array', 'hire_date' => 'date', 'contract_end_date' => 'date', 'portal_access_enabled' => 'boolean', 'archived_at' => 'datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'organization_membership_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
