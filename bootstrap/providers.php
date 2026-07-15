<?php

declare(strict_types=1);
use App\Providers\AppServiceProvider;
use Core\AIGateway\Providers\AIGatewayServiceProvider;
use Core\Analytics\Providers\AnalyticsServiceProvider;
use Core\Api\Providers\ApiServiceProvider;
use Core\AuditLogs\Providers\AuditLogsServiceProvider;
use Core\Auth\Providers\CoreAuthServiceProvider;
use Core\Backup\Providers\BackupServiceProvider;
use Core\Branding\Providers\BrandingServiceProvider;
use Core\Deployment\Providers\DeploymentServiceProvider;
use Core\FeatureFlags\Providers\FeatureFlagsServiceProvider;
use Core\Health\Providers\HealthServiceProvider;
use Core\Identity\Providers\IdentityServiceProvider;
use Core\Installer\Providers\InstallerServiceProvider;
use Core\Licensing\Providers\LicensingServiceProvider;
use Core\Mail\Providers\MailServiceProvider;
use Core\Modules\Providers\ModulesServiceProvider;
use Core\Notifications\Providers\NotificationsServiceProvider;
use Core\RBAC\Providers\RBACServiceProvider;
use Core\Scheduler\Providers\SchedulerServiceProvider;
use Core\Security\Providers\SecurityServiceProvider;
use Core\Settings\Providers\SettingsServiceProvider;
use Core\Storage\Providers\StorageServiceProvider;
use Core\Subscriptions\Providers\SubscriptionsServiceProvider;
use Core\Support\Providers\SupportServiceProvider;
use Core\Users\Providers\UsersServiceProvider;
use Modules\Academics\Providers\AcademicsServiceProvider;
use Modules\Attendance\Providers\AttendanceServiceProvider;
use Modules\Learners\Providers\LearnersServiceProvider;
use Modules\Organizations\Providers\OrganizationsServiceProvider;
use Modules\Staff\Providers\StaffServiceProvider;

return [
    AppServiceProvider::class,

    // Core service providers — each Core service registers its own
    // bindings, routes, and event->listener maps. Order matters where
    // a service depends on another (e.g. Auth depends on Users).
    SupportServiceProvider::class,
    ApiServiceProvider::class,
    UsersServiceProvider::class,
    CoreAuthServiceProvider::class,
    RBACServiceProvider::class,
    IdentityServiceProvider::class,
    AuditLogsServiceProvider::class,
    SettingsServiceProvider::class,
    BrandingServiceProvider::class,
    NotificationsServiceProvider::class,
    StorageServiceProvider::class,
    MailServiceProvider::class,
    AIGatewayServiceProvider::class,
    ModulesServiceProvider::class,
    LicensingServiceProvider::class,
    SubscriptionsServiceProvider::class,
    DeploymentServiceProvider::class,
    HealthServiceProvider::class,
    FeatureFlagsServiceProvider::class,
    AnalyticsServiceProvider::class,
    SecurityServiceProvider::class,
    BackupServiceProvider::class,
    SchedulerServiceProvider::class,
    InstallerServiceProvider::class,

    // Module providers — see docs/architecture/module-system.md. Each
    // module bootstraps itself exactly like a Core service (routes +
    // migrations); Core\Modules\Application\ModuleManager separately
    // tracks per-tenant enablement as data, not code loading.
    AcademicsServiceProvider::class,
    OrganizationsServiceProvider::class,
    StaffServiceProvider::class,
    LearnersServiceProvider::class,
    AttendanceServiceProvider::class,
];
