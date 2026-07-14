<?php

declare(strict_types=1);

namespace Core\RBAC\Infrastructure\Models;

use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * A named, configurable collection of Permissions. Roles are
 * data-driven — no role's behaviour is hardcoded in application code,
 * per docs/security/rbac.md and the "never hardcode roles" project rule.
 */
final class Role extends Model
{
    use HasUuids;

    protected $table = 'roles';

    protected $fillable = ['organization_id', 'name', 'description', 'is_system'];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions', 'role_id', 'permission_id');
    }

    public function users(): MorphToMany
    {
        return $this->morphedByMany(User::class, 'model', 'model_has_roles', 'role_id', 'model_id');
    }
}
