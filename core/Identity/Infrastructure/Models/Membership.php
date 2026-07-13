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
use Modules\Organizations\Infrastructure\Models\Organization;

final class Membership extends Model
{
    use HasUuidPrimaryKey; use SoftDeletes;
    protected $table = 'organization_memberships';
    protected $fillable = ['user_id','organization_id','role_id','status','joined_at','invited_by','accepted_at','last_active_at','is_primary','is_default','invitation_token','invitation_expires_at'];
    protected function casts(): array { return ['status'=>MembershipStatus::class,'joined_at'=>'datetime','accepted_at'=>'datetime','last_active_at'=>'datetime','invitation_expires_at'=>'datetime','is_primary'=>'boolean','is_default'=>'boolean']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function role(): BelongsTo { return $this->belongsTo(Role::class); }
}
