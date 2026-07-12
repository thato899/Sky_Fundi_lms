# core/Scheduler

**Purpose**: wires the platform's recurring maintenance commands into Laravel's own scheduler (`Illuminate\Console\Scheduling\Schedule`). Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**Not a scheduler implementation of its own** — Laravel's scheduler is used directly, per the brief ("Prepare Laravel Scheduler"). This folder is where the platform's cross-cutting scheduled commands live and get registered, since none of them belong to any single other Core service.

**Responsibilities** (`Console/`):
- `RunHealthChecksCommand` (`platform:health-check`, hourly) — runs [`core/Health`](../Health/README.md)'s checks, logs the result.
- `ValidateLicensesCommand` (`platform:validate-licenses`, daily) — calls `Core\Licensing\Application\LicenseService::expireOverdueLicenses()`.
- `ValidateSubscriptionsCommand` (`platform:validate-subscriptions`, daily) — calls `Core\Subscriptions\Application\SubscriptionService::suspendOverdueGracePeriods()`.
- `CleanTemporaryFilesCommand` (`platform:clean-temp`, daily) — deletes stale files under `storage/app/temp` (reserved scratch space for future report/import/export/AI-attachment work — nothing writes there yet).
- `CleanQueueCommand` (`platform:clean-queue`, weekly) — prunes old `failed_jobs` rows.
- `CleanAiCacheCommand` (`platform:clean-ai-cache`, daily) — reserved cleanup for a future AI response cache (`Core\AIGateway` doesn't cache responses yet).
- [`core/Backup`](../Backup/README.md)'s `platform:backup` is also scheduled here (weekly), since Backup owns the command but Scheduler owns the platform's cron table.

**Allowed dependencies**: `Core\Health`, `Core\Licensing`, `Core\Subscriptions`, `Core\Backup`, `Core\Logging`. Never a module.

**Requires**: the standard Laravel cron entry running `php artisan schedule:run` every minute — see [Deployment](../../docs/deployment/environments.md#infrastructure-assumptions).
