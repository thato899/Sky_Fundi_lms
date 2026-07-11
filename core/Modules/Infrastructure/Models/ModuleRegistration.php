<?php

declare(strict_types=1);

namespace Core\Modules\Infrastructure\Models;

use Core\Modules\Domain\Enums\ModuleStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A module known to this platform installation. See
 * docs/architecture/module-system.md. No modules are shipped in this
 * repository yet — this table is empty until the first module is
 * installed.
 */
final class ModuleRegistration extends Model
{
    use HasUuids;

    protected $table = 'modules';

    protected $fillable = [
        'name', 'display_name', 'version', 'description', 'author',
        'status', 'dependencies', 'tenant_types', 'enabled_for_tenants',
        'installed_at', 'enabled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ModuleStatus::class,
            'dependencies' => 'array',
            'tenant_types' => 'array',
            'enabled_for_tenants' => 'array',
            'installed_at' => 'datetime',
            'enabled_at' => 'datetime',
        ];
    }
}
