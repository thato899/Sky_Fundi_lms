<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Platform Permission Catalog
|--------------------------------------------------------------------------
|
| Source of truth for Core-owned permissions, consumed by
| database/seeders/PermissionSeeder.php. Module permissions are declared
| in each module's own manifest per
| docs/architecture/module-system.md#module-manifest-modulejson and are
| registered into the same permissions table when a module is enabled —
| this file only ever lists Core permissions, never module ones.
|
| Naming: <module>.<resource>.<action> — see docs/naming-conventions.md.
| Core permissions use the "core" module segment.
|
*/

return [
    'core.users.manage' => 'Create, update, suspend, and delete platform users',
    'core.users.view' => 'View platform user records',
    'core.roles.manage' => 'Create, update, and delete roles and their permission assignments',
    'core.permissions.manage' => 'Assign or revoke individual permissions',
    'core.branding.manage' => 'Update platform branding (logo, colours, name)',
    'core.settings.manage' => 'Update global platform settings',
    'core.billing.manage' => 'Manage tenant billing and subscriptions',
    'core.ai.manage' => 'Configure AI Gateway providers and routing',
    'core.logs.view' => 'View application, security, and audit logs',
    'core.modules.manage' => 'Install, enable, disable, update, or remove modules',
];
