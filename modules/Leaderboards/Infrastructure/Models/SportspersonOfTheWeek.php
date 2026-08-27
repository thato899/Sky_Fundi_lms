<?php

declare(strict_types=1);

namespace Modules\Leaderboards\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;

final class SportspersonOfTheWeek extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'sportsperson_of_the_week';

    protected $fillable = ['uuid', 'organization_id', 'learner_profile_id', 'week_start_date', 'citation', 'is_published', 'posted_by', 'published_at'];

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
        return ['week_start_date' => 'date', 'is_published' => 'boolean', 'published_at' => 'datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
