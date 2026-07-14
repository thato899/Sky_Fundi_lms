<?php

declare(strict_types=1);

namespace Modules\Learners\Infrastructure\Models;

use Core\Identity\Infrastructure\Models\Membership;
use Core\Support\Traits\HasUuidPrimaryKey;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Curriculum;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Learners\Database\Factories\LearnerProfileFactory;
use Modules\Learners\Domain\Enums\LearnerStatus;
use Modules\Organizations\Infrastructure\Models\Organization;

final class LearnerProfile extends Model
{
    use HasFactory;
    use HasUuidPrimaryKey;
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'organization_id', 'user_id', 'organization_membership_id',
        'learner_number', 'admission_number', 'first_name', 'middle_name',
        'last_name', 'preferred_name', 'date_of_birth', 'profile_photo_path',
        'current_academic_year_id', 'current_grade_id', 'current_class_id',
        'curriculum_id', 'admission_date', 'expected_completion_date',
        'previous_institution', 'language_of_instruction', 'home_language',
        'learning_mode', 'learner_email', 'learner_phone', 'residential_address',
        'city', 'province', 'country', 'postal_code', 'learner_status',
        'academic_status', 'onboarding_status', 'portal_access_enabled',
        'metadata', 'created_by', 'updated_by', 'archived_at',
    ];

    protected $attributes = [
        'learner_status' => 'pending',
        'onboarding_status' => 'pending',
        'portal_access_enabled' => false,
    ];

    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }

    protected function casts(): array
    {
        return [
            'learner_status' => LearnerStatus::class,
            'metadata' => 'array',
            'date_of_birth' => 'date',
            'admission_date' => 'date',
            'expected_completion_date' => 'date',
            'portal_access_enabled' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    protected static function newFactory(): LearnerProfileFactory
    {
        return LearnerProfileFactory::new();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organizationMembership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function currentAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'current_academic_year_id');
    }

    public function currentGrade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'current_grade_id');
    }

    public function currentClass(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class, 'current_class_id');
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(LearnerStatusHistory::class);
    }
}
