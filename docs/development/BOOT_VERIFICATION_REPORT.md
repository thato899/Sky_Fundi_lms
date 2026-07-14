# Boot verification report

Date: 2026-07-14  
Source revision: `c1188f663fe70b1704fa148953b8d63080e9e3ce` (GitHub `main`)

## Static checks completed

- Confirmed real Laravel directories at repository root: `app`, `bootstrap`, `config`, `core`, `database`, `docker`, `docs`, `modules`, `public`, `resources`, `routes`, `storage`, and `tests`.
- Confirmed required root files: `artisan`, `composer.json`, `Dockerfile`, `compose.yaml`, `README.md`, and `.env.example`.
- Replaced shared runtime initialisation with a dedicated `init` Compose service. App, queue, and scheduler now require `init` to finish successfully.
- Added bounded MySQL readiness retries, safe `.env` creation, conditional application-key generation, and an application `/up` HTTP health check.
- Added `scripts/verify-archive.py` for archive-path and required-root-file checks.

## Archive verification completed

The first Windows archive writer was rejected because it produced backslash entries. The delivered archive was then created with explicitly normalised POSIX-relative entry names and extracted into a fresh directory for verification.

```text
Archive: Sky_Fundi_lms-final-stabilized-posix2.zip
Entries: 600
Entries containing backslashes or unsafe paths: 0
Missing required root files: none
Missing extracted expected directories: none
.github preserved: true
SHA-256: 21EF399951B28CA4F6F148F7501A6629E62CF8BFC45FE0DFB487EA139233F6A7
```

## Commands actually executed

```text
Get-Command docker, php, composer, python
docker: NOT FOUND
php: NOT FOUND
composer: NOT FOUND
python: NOT FOUND
```

## Commands not executed and why

The host has no Docker, PHP, Composer, or Python executable. Consequently the following could not be executed in this environment:

```text
docker compose build
docker compose up --build init
composer validate --strict
composer install --no-interaction --prefer-dist
composer check-platform-reqs
php artisan migrate:status
php artisan test
```

No migration, Composer validation, platform check, or test result is claimed. `composer.lock` could not be honestly generated without Composer and is therefore still absent; generate it as documented before declaring a reproducible dependency build.
