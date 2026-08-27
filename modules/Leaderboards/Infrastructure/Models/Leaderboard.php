<?php

declare(strict_types=1);

namespace Modules\Leaderboards\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Leaderboards\Domain\Enums\LeaderboardType;
use Modules\Leaderboards\Domain\Enums\LeaderboardVisibility;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Reports\Infrastructure\Models\ReportingPeriod;

final class Leaderboard extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'uuid', 'organization_id', 'type', 'name', 'subject_id', 'grade_id', 'class_id',
        'reporting_period_id', 'period_start_date', 'period_end_date',
        'visibility', 'generated_at', 'generated_by', 'published_at', 'published_by',
    ];

    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'type' => LeaderboardType::class,
            'visibility' => LeaderboardVisibility::class,
            'period_start_date' => 'date',
            'period_end_date' => 'date',
            'generated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class, 'class_id');
    }

    public function reportingPeriod(): BelongsTo
    {
        return $this->belongsTo(ReportingPeriod::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LeaderboardEntry::class);
    }

    public function isPublished(): bool
    {
        return $this->getAttribute('visibility') === LeaderboardVisibility::Published;
    }
}
