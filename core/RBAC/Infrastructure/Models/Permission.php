<?php

declare(strict_types=1);

namespace Core\RBAC\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A single grantable capability, e.g. "core.users.manage". Permissions
 * are never hardcoded into role checks — every authorization decision
 * resolves to a row in this table. See docs/security/rbac.md.
 */
final class Permission extends Model
{
    use HasUuids;

    protected $table = 'permissions';

    protected $fillable = ['name', 'description', 'module'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_has_permissions', 'permission_id', 'role_id');
    }
}
