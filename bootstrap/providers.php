<?php

declare(strict_types=1);

return [
    App\Providers\AppServiceProvider::class,

    // Core service providers — each Core service registers its own
    // bindings, routes, and event->listener maps. Order matters where
    // a service depends on another (e.g. Auth depends on Users).
    Core\Users\Providers\UsersServiceProvider::class,
    Core\Auth\Providers\CoreAuthServiceProvider::class,
    Core\RBAC\Providers\RBACServiceProvider::class,
    Core\AuditLogs\Providers\AuditLogsServiceProvider::class,
    Core\Settings\Providers\SettingsServiceProvider::class,
    Core\Branding\Providers\BrandingServiceProvider::class,
    Core\Notifications\Providers\NotificationsServiceProvider::class,
    Core\Storage\Providers\StorageServiceProvider::class,
    Core\AIGateway\Providers\AIGatewayServiceProvider::class,
    Core\Modules\Providers\ModulesServiceProvider::class,
];
