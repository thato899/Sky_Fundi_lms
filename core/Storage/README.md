# core/Storage

**Purpose**: an abstraction over file persistence so Core services and future modules never depend on a specific storage backend. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**Responsibilities**:
- `Contracts/FileStorageInterface` — `put/get/exists/delete/url/size/driverName`. This is what every caller type-hints — never Laravel's `Storage` facade directly, and never a cloud SDK directly.
- `Infrastructure/Providers/LocalFileStorage` — the only implementation shipped today, wrapping the local/public disks from `config/filesystems.php`.
- `Application/StorageManager` — resolves the configured driver; throws `Exceptions\StorageDriverNotAvailableException` for a disk name it doesn't yet know how to build (`s3`, `azure`, `gcs`).
- `Providers/StorageServiceProvider` binds `FileStorageInterface` to the manager's default-disk resolution, so a plain constructor type-hint of the interface is all any consumer needs.

**Allowed dependencies**: none beyond the framework filesystem layer. Never a module.

**Future usage**: S3, Azure, and Google Cloud drivers are added purely as new classes implementing `FileStorageInterface` plus a new `match()` arm in `StorageManager::makeDriver()` — no caller code changes when that happens, per the interface-first design mandated in the original brief.
