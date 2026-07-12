# core/Storage

**Purpose**: an abstraction over file persistence so Core services and future modules never depend on a specific storage backend. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**Responsibilities**:
- `Contracts/FileStorageInterface` — `put/get/exists/delete/url/size/driverName/isAvailable`. This is what every caller type-hints — never Laravel's `Storage` facade directly, and never a cloud SDK directly.
- `Infrastructure/Providers/LocalFileStorage` — wraps the local/public disks. Always available (no external credentials).
- `Infrastructure/Providers/S3FileStorage` — fully implemented, wraps the `s3` Laravel disk (`league/flysystem-aws-s3-v3`, declared in `composer.json`). Reports available once `AWS_ACCESS_KEY_ID`/`AWS_BUCKET` are configured.
- `Infrastructure/Providers/{AzureBlobFileStorage,GoogleCloudFileStorage}` — real, registered, plug-and-play implementations of `FileStorageInterface` (via `AbstractPlaceholderFileStorage`) that report `isAvailable(): false` and throw a clear `StorageDriverNotAvailableException` if ever selected, rather than being silently absent from the registry — mirrors [AI Gateway](../AIGateway/README.md)'s placeholder pattern exactly. Implementing each fully means adding the matching Flysystem package and registering a custom Laravel filesystem driver.
- `Application/StorageProviderFactory` — resolves a `FileStorageInterface` for a named disk purely from that disk's `driver` key in `config/filesystems.php` — deliberately reusing Laravel's own disk config rather than a second, parallel config file.
- `Application/StorageProviderRegistry` — lists every configured disk and which are currently usable (used by the [Health Centre](../Health/README.md) and the admin storage settings screen).
- `Application/StorageManager` — the entry point every caller depends on; adds per-request memoization on top of the factory.
- `Providers/StorageServiceProvider` binds `FileStorageInterface` to the manager's default-disk resolution, so a plain constructor type-hint of the interface is all any consumer needs.

**Allowed dependencies**: `Core\Support`. Never a module.

**Routes**: `GET /api/v1/storage/disks` (permission `core.settings.manage`) — lists disks, drivers, and availability.

**Future usage**: a real Azure/GCS driver is added purely as a rewritten provider class plus its Flysystem package — no caller code changes, per the interface-first design mandated in the original brief.
