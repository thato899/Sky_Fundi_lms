# core/Installer

**Purpose**: first-run platform installation — application name, mail/storage/AI provider selection, branding, localization, the initial administrator account, and a license key. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**Workflow**: `php artisan platform:install` runs every step in `config('installer.steps')`, in order, interactively prompting for each step's input. Already-complete steps (detected via `InstallerStepInterface::isComplete()` — e.g. a `system_name` setting already exists, a Super Admin user already exists) are skipped, so the command is safe to re-run after a partial install. Once every step reports complete, `Application/InstallerService` marks the platform installed (`Settings` key `platform_installed`).

**Steps** (`Application/Steps/`), each a thin `InstallerStepInterface` implementation delegating to the Core service that actually owns the concern — the installer never duplicates validation or writes a table directly:
| Step | Delegates to |
|---|---|
| `ApplicationStep` | `Core\Settings` |
| `LocalizationStep` | `Core\Settings` |
| `MailStep` | `Core\Mail` (`MailProviderRegistry`) |
| `StorageStep` | `Core\Storage` (`StorageProviderRegistry`) |
| `AIProviderStep` | `Core\AIGateway` (`ProviderRegistry`) |
| `BrandingStep` | `Core\Branding` |
| `AdministratorStep` | `Core\Users` + `Core\RBAC` (creates the user, assigns the seeded "Super Admin" role — mirrors `database/seeders/SuperAdminUserSeeder.php` but for an interactive install instead of `db:seed`) |
| `LicenseStep` | `Core\Settings` (records the key only — see the step's own docblock for why it doesn't create a `Core\Licensing\License` record yet) |

**Queue and database are deliberately not installer steps**: both must already be reachable before `php artisan` can run at all (the command itself needs a working queue/database config to even boot), so there is nothing for an installer step to configure — see [Environment Variables](../../docs/environment-variables.md) for how those are supplied.

**Allowed dependencies**: `Core\Settings`, `Core\Mail`, `Core\Storage`, `Core\AIGateway`, `Core\Branding`, `Core\Users`, `Core\RBAC`. Never a module.

**Future usage**: a web-based installer UI would call `Core\Installer\Application\InstallerService::status()`/`runStep()` through a new, unauthenticated-until-installed HTTP controller — not built here; see the console command's own docblock for why it's deferred.
