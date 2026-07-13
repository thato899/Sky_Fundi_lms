<?php

declare(strict_types=1);

namespace Modules\Organizations\Database\Seeders;

use Core\RBAC\Application\RoleService;
use Illuminate\Database\Seeder;

final class OrganizationsPermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'organizations.view' => 'View organizations',
        'organizations.manage' => 'Create, update, suspend, and delete organizations',
        'organizations.branding.manage' => 'Manage organization branding',
        'organizations.ai.manage' => 'Configure organization AI through the AI Gateway',
        'organizations.modules.manage' => 'Assign organization modules',
        'organizations.users.manage' => 'Assign organization administrators and manage organization users',
        'organizations.settings.manage' => 'Manage organization settings',
        'organizations.security.manage' => 'Manage organization security settings',
    ];

    public function run(RoleService $roles): void
    {
        foreach (self::PERMISSIONS as $name => $description) $roles->registerPermission($name, module: 'organizations', description: $description);
    }
}
