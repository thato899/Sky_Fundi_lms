<?php

declare(strict_types=1);

namespace Modules\Learners\Infrastructure\Models;

use Core\Identity\Infrastructure\Models\Membership;
use Core\Support\Traits\HasUuidPrimaryKey;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Learners\Domain\Enums\GuardianStatus;
use Modules\Organizations\Infrastructure\Models\Organization;

/**
 * @property string $uuid
 * @property string $organization_id
 * @property string|null $organization_membership_id
 * @property GuardianStatus $status
 * @property Carbon|null $archived_at
 * @property Carbon|null $deleted_at
 * @property User|null $user
 * @property Membership|null $organizationMembership
 * @property Collection<int, LearnerGuardianRelationship> $relationships
 */
final class GuardianProfile extends Model
{
    use HasUuidPrimaryKey;
    use SoftDeletes;

    protected $fillable = ['organization_id', 'user_id', 'organization_membership_id', 'first_name', 'last_name', 'email', 'phone', 'preferred_communication_channel', 'address', 'status', 'created_by', 'updated_by', 'archived_at'];

    protected $attributes = ['preferred_communication_channel' => 'email', 'status' => 'active'];

    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }

    protected function casts(): array
    {
        return ['status' => GuardianStatus::class, 'archived_at' => 'datetime'];
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

    public function relationships(): HasMany
    {
        return $this->hasMany(LearnerGuardianRelationship::class);
    }
}
