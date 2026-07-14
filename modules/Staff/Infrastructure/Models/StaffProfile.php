<?php

declare(strict_types=1);

namespace Modules\Staff\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class StaffProfile extends Model
{
    use HasUuidPrimaryKey,SoftDeletes;

    protected $fillable = ['organization_id', 'organization_membership_id', 'user_id', 'employee_number', 'staff_type', 'job_title', 'employment_type', 'employment_status', 'department_id', 'reports_to_staff_id', 'hire_date', 'contract_end_date', 'qualification_summary', 'specializations', 'languages', 'weekly_working_hours', 'availability_status', 'work_email', 'work_phone', 'onboarding_status', 'archived_at'];

    protected function casts(): array
    {
        return ['specializations' => 'array', 'languages' => 'array', 'hire_date' => 'date', 'contract_end_date' => 'date', 'archived_at' => 'datetime'];
    }
}
