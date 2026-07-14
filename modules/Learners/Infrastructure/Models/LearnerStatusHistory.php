<?php

declare(strict_types=1);

namespace Modules\Learners\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Modules\Learners\Domain\Enums\LearnerStatus;
use Modules\Organizations\Infrastructure\Models\Organization;

final class LearnerStatusHistory extends Model
{
    use HasUuidPrimaryKey;

    public $timestamps = false;

    protected $table = 'learner_status_histories';

    protected $fillable = [
        'organization_id', 'learner_profile_id', 'previous_status', 'new_status',
        'actor_id', 'reason', 'changed_at',
    ];

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Learner status history is immutable.'));
        self::deleting(fn () => throw new LogicException('Learner status history is immutable.'));
    }

    protected function casts(): array
    {
        return [
            'previous_status' => LearnerStatus::class,
            'new_status' => LearnerStatus::class,
            'changed_at' => 'immutable_datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function learnerProfile(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
