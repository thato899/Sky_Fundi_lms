<?php

declare(strict_types=1);

namespace Core\Users\Infrastructure\Models;

use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Domain\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * The platform's User model.
 *
 * Deliberately lives under Core\Users rather than App\Models, per
 * docs/naming-conventions.md — identity is a Core concern, not an
 * application-root concern, and every module ultimately references this
 * class rather than duplicating a user concept of its own.
 *
 * Eloquent models are an Infrastructure-layer concern per
 * docs/architecture/clean-architecture.md; domain rules that don't need
 * persistence live in Core\Users\Domain instead (see UserStatus).
 */
final class User extends Model implements AuthenticatableContract, CanResetPasswordContract, MustVerifyEmailContract
{
    use Authenticatable;
    use CanResetPassword;
    use HasApiTokens;
    use HasFactory;
    use HasUuids;
    use MustVerifyEmail;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'timezone',
        'locale',
        'profile_photo_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'locked_at' => 'datetime',
            'failed_login_attempts' => 'integer',
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * Roles assigned to this user. Permissions are never assigned
     * directly to a role's individual actions being hardcoded — see
     * docs/security/rbac.md. A user may also hold direct permission
     * overrides via the `permissions` relation below.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'model_has_roles', 'model_id', 'role_id')
            ->where('model_has_roles.model_type', self::class);
    }

    /**
     * Direct permission overrides, independent of role assignment.
     * Used sparingly — most authorization should flow through roles.
     */
    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'model_has_permissions', 'model_id', 'permission_id')
            ->where('model_has_permissions.model_type', self::class);
    }

    /** Tenant access belongs to memberships, never to the identity itself. */
    public function memberships(): HasMany
    {
        return $this->hasMany(\Core\Identity\Infrastructure\Models\Membership::class);
    }

    public function isLocked(): bool
    {
        return $this->status === UserStatus::Locked;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }
}
