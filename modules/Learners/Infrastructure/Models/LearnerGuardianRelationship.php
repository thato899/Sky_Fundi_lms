<?php

declare(strict_types=1);

namespace Modules\Learners\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $status
 * @property bool $receives_academic_communication
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_until
 * @property Carbon|null $deleted_at
 * @property LearnerProfile $learner
 * @property GuardianProfile $guardian
 */
final class LearnerGuardianRelationship extends Model
{
    use HasUuidPrimaryKey;
    use SoftDeletes;

    protected $fillable = ['organization_id', 'learner_profile_id', 'guardian_profile_id', 'relationship_type', 'is_primary', 'is_emergency_contact', 'is_authorized_pickup', 'receives_academic_communication', 'receives_financial_communication', 'status', 'effective_from', 'effective_until', 'created_by', 'updated_by'];

    protected $attributes = ['is_primary' => false, 'is_emergency_contact' => false, 'is_authorized_pickup' => false, 'receives_academic_communication' => true, 'receives_financial_communication' => false, 'status' => 'active'];

    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'is_emergency_contact' => 'boolean', 'is_authorized_pickup' => 'boolean', 'receives_academic_communication' => 'boolean', 'receives_financial_communication' => 'boolean', 'effective_from' => 'date', 'effective_until' => 'date'];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(GuardianProfile::class, 'guardian_profile_id');
    }
}
