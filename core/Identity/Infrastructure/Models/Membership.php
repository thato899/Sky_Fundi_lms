<?php

declare(strict_types=1);

namespace Core\Identity\Infrastructure\Models;

use Core\Identity\Domain\Enums\MembershipStatus;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Support\Traits\HasUuidPrimaryKey;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Organizations\Infrastructure\Models\Organization;

/**
 * @property string|null $user_id
 * @property string $organization_id
 * @property MembershipStatus $status
 * @property string|null $invited_email
 * @property string|null $invitation_token
 * @property Carbon|null $invitation_expires_at
 * @property Carbon|null $invitation_sent_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 * @property int $resend_count
 */
final class Membership extends Model
{
    use HasUuidPrimaryKey;
    use SoftDeletes;

    protected $table = 'organization_memberships';

    protected $fillable = ['user_id', 'organization_id', 'role_id', 'status', 'joined_at', 'invited_by', 'accepted_at', 'last_active_at', 'is_primary', 'is_default', 'invitation_token', 'invited_email', 'invitation_expires_at', 'invitation_sent_at', 'revoked_at', 'resend_count'];

    protected function casts(): array
    {
        return ['status' => MembershipStatus::class, 'joined_at' => 'datetime', 'accepted_at' => 'datetime', 'last_active_at' => 'datetime', 'invitation_expires_at' => 'datetime', 'invitation_sent_at' => 'datetime', 'revoked_at' => 'datetime', 'resend_count' => 'integer', 'is_primary' => 'boolean', 'is_default' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
