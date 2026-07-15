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
    'organization.dashboard.view' => 'View the active organization dashboard',
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
    'core.licenses.manage' => 'Issue, activate, suspend, cancel, and renew licenses',
    'core.deployment.manage' => 'Create and update deployment profiles',
    'core.health.view' => 'View platform health and system status checks',
    'core.security.manage' => 'Manage trusted devices, IP restrictions, and session security',
    'core.feature-flags.manage' => 'Create, update, and toggle feature flags',
    'core.analytics.view' => 'View platform analytics data',
    'core.backups.manage' => 'Trigger and view platform backups',
];
