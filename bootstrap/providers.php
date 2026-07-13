<?php

declare(strict_types=1);

return [
    App\Providers\AppServiceProvider::class,

    // Core service providers — each Core service registers its own
    // bindings, routes, and event->listener maps. Order matters where
    // a service depends on another (e.g. Auth depends on Users).
    Core\Support\Providers\SupportServiceProvider::class,
    Core\Api\Providers\ApiServiceProvider::class,
    Core\Users\Providers\UsersServiceProvider::class,
    Core\Auth\Providers\CoreAuthServiceProvider::class,
    Core\RBAC\Providers\RBACServiceProvider::class,
    Core\AuditLogs\Providers\AuditLogsServiceProvider::class,
    Core\Settings\Providers\SettingsServiceProvider::class,
    Core\Branding\Providers\BrandingServiceProvider::class,
    Core\Notifications\Providers\NotificationsServiceProvider::class,
    Core\Storage\Providers\StorageServiceProvider::class,
    Core\Mail\Providers\MailServiceProvider::class,
    Core\AIGateway\Providers\AIGatewayServiceProvider::class,
    Core\Modules\Providers\ModulesServiceProvider::class,
    Core\Licensing\Providers\LicensingServiceProvider::class,
    Core\Subscriptions\Providers\SubscriptionsServiceProvider::class,
    Core\Deployment\Providers\DeploymentServiceProvider::class,
    Core\Health\Providers\HealthServiceProvider::class,
    Core\FeatureFlags\Providers\FeatureFlagsServiceProvider::class,
    Core\Analytics\Providers\AnalyticsServiceProvider::class,
    Core\Security\Providers\SecurityServiceProvider::class,
    Core\Backup\Providers\BackupServiceProvider::class,
    Core\Scheduler\Providers\SchedulerServiceProvider::class,
    Core\Installer\Providers\InstallerServiceProvider::class,

    // Module providers — see docs/architecture/module-system.md. Each
    // module bootstraps itself exactly like a Core service (routes +
    // migrations); Core\Modules\Application\ModuleManager separately
    // tracks per-tenant enablement as data, not code loading.
    Modules\Academics\Providers\AcademicsServiceProvider::class,
    Modules\Organizations\Providers\OrganizationsServiceProvider::class,
];
