# core/Backup

**Purpose**: backup — not restore — of the database, storage, configuration, and logs. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**Restore is explicitly out of scope** for this version, per the brief ("Future Restore"). What's here produces backup artefacts; restoring from them is a manual, deployment-specific operations task until restore tooling is built.

**Responsibilities**:
- `Contracts/BackupTargetInterface` — one target, one archive file out.
- `Infrastructure/Targets/DatabaseBackupTarget` — SQLite: a file copy. MySQL: shells out to `mysqldump` via `Illuminate\Support\Facades\Process`, passing the password through the `MYSQL_PWD` environment variable rather than a command-line argument (avoids it appearing in the process list).
- `Infrastructure/Targets/{Storage,Configuration,Logs}BackupTarget` — zip `storage/app`, `config/*.php`, and `storage/logs` respectively via PHP's `ZipArchive`. `ConfigurationBackupTarget` **never** includes the real `.env` file — only `config/*.php` — per [Secrets Management](../../docs/security/policies.md#secrets-management).
- `Application/BackupManager::runAll()` — runs every target listed in `config/backup.php`, each independently try/caught so one failing target doesn't stop the rest. Fires `Events\BackupCompleted` (`Auditable`).
- `Console/RunBackupCommand` — `php artisan platform:backup`.

**Allowed dependencies**: `Core\AuditLogs` (via the `Auditable` event). Never a module.

**Future usage**: scheduling (`php artisan schedule:run` calling this command) is wired in [`core/Scheduler`](../Scheduler/README.md); restore tooling and off-site upload (via `Core\Storage`) are future work.
