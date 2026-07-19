# Native cPanel deployment

This runbook deploys Sky Fundi to a cPanel account with LiteSpeed, SSH, cron,
MariaDB, and AutoSSL. It does not replace the Docker/VPS workflow.

## Architecture and prerequisites

Select **PHP 8.3 or newer** in cPanel MultiPHP Manager. Although some hosting
plans offer PHP 8.2, this repository requires PHP 8.3. Enable `ctype`, `curl`,
`dom`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`,
`xml`, and `zip`. Composer 2, Git, `proc_open`, SSH, MariaDB/InnoDB, symlink
support, and cron are also required. Supervisor, Docker, Redis, systemd, root,
and `sudo` are not required.

Use this layout, replacing `CPANEL_USER`:

```text
/home/CPANEL_USER/
|-- repositories/
|   `-- sky-fundi/
|       `-- current/
|-- sky-fundi-shared/
|   |-- .env
|   |-- storage/
|   `-- backups/
|-- logs/
`-- public_html/
    `-- skyfundi/
```

The preferred document root for the domain/subdomain is:

```text
/home/CPANEL_USER/repositories/sky-fundi/current/public
```

Set it in cPanel Domains and let AutoSSL manage HTTPS. Do not add an
application-level HTTPS rewrite: cPanel/LiteSpeed should redirect HTTP, which
avoids proxy redirect loops. The bundled `public/.htaccess` preserves
authorization headers, disables indexes, denies dotfiles, serves static files
directly, and sends other requests to `public/index.php`.

Never place the whole repository under `public_html`.

### Fallback document root

Only if cPanel cannot change the document root, keep the repository and shared
files outside `public_html`, copy only the contents of `public/` into
`public_html/skyfundi`, and change the two paths in the copied `index.php` to
absolute private paths:

```php
require '/home/CPANEL_USER/repositories/sky-fundi/current/vendor/autoload.php';
$app = require_once '/home/CPANEL_USER/repositories/sky-fundi/current/bootstrap/app.php';
```

Create `public_html/skyfundi/storage` as a link to
`sky-fundi-shared/storage/app/public`. Repeat the public-file copy after an
application update. Confirm that `/.env`, `/composer.json`, `/vendor/`,
`/storage/logs/`, and `/config/app.php` return 403 or 404.

## First deployment checklist

1. Create a MariaDB database and least-privilege database user in cPanel, then
   grant the user all privileges on that database.
2. Clone the reviewed production branch to
   `/home/CPANEL_USER/repositories/sky-fundi/current`.
3. Copy `.env.cpanel.example` to `/home/CPANEL_USER/sky-fundi-shared/.env`,
   replace every `CHANGE_ME` placeholder, and run `chmod 600` on it.
4. Generate the application key without printing it into shell history:

   ```bash
   cd /home/CPANEL_USER/repositories/sky-fundi/current
   php artisan key:generate --show
   ```

   Paste the result into the private shared `.env`; never commit it.
5. Link the private environment into the checkout:

   ```bash
   ln -s /home/CPANEL_USER/sky-fundi-shared/.env .env
   ```

6. Run the deployment:

   ```bash
   chmod 755 scripts/deploy-cpanel.sh
   ./scripts/deploy-cpanel.sh --confirm-production
   ```

On its first run, the script moves the repository storage skeleton to
`sky-fundi-shared/storage` and replaces it with a symlink. Later Git updates
therefore cannot overwrite uploads. Private learner documents remain under
`storage/app/private`; only `storage/app/public` is exposed by `public/storage`.

## Environment and database

Use `.env.cpanel.example` as the contract. Required production choices are:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR_DOMAIN
DB_CONNECTION=mysql
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
DB_QUEUE_RETRY_AFTER=120
```

The existing migrations create `cache`, `cache_locks`, `sessions`, `jobs`,
`job_batches`, and `failed_jobs`. `retry_after=120` is greater than the
50-second queue timeout. Run this secret-safe preflight after configuration:

```bash
php artisan platform:validate-environment --cpanel
```

It verifies PHP/extensions, production flags, HTTPS, database connectivity and
tables, database drivers, writable paths, storage link, mail configuration,
and enabled AI provider configuration. It does not contact SMTP or AI services
and never prints configured values. Keep Redis disabled/unselected.

## Cron jobs

Find the account PHP binary with:

```bash
which php
php -v
```

Use the returned absolute path in cPanel Cron Jobs. Process one bounded,
non-overlapping database worker each minute:

```cron
* * * * * cd /home/CPANEL_USER/repositories/sky-fundi/current && /usr/local/bin/php artisan platform:process-queue >> /home/CPANEL_USER/logs/sky-fundi-queue.log 2>&1
```

The command obtains a database-cache lock and invokes one worker with
`--stop-when-empty --tries=3 --timeout=50 --memory=192 --max-time=50`. A second
invocation exits safely while the lock is held. Use one queue cron only on this
one-CPU plan.

Run Laravel’s scheduler once per minute:

```cron
* * * * * cd /home/CPANEL_USER/repositories/sky-fundi/current && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

No sub-minute scheduler is required. Scheduled maintenance commands use
Laravel cache locks to avoid overlap. Review the queue log and `failed_jobs`
regularly.

## GitHub deployment and updates

### Preferred: cPanel Git Version Control

Create a private repository deployment in cPanel Git Version Control at the
repository path above. Authenticate with a read-only GitHub deploy key or the
host’s supported GitHub integration, check out the reviewed production branch,
update it in cPanel, then run `scripts/deploy-cpanel.sh --confirm-production`
over SSH. Never save a personal access token in this repository or `.env`.

### SSH

For a reviewed production release:

```bash
cd /home/CPANEL_USER/repositories/sky-fundi/current
git fetch origin
git checkout main
git pull --ff-only origin main
./scripts/deploy-cpanel.sh --confirm-production
```

Do not deploy an unreviewed feature branch. Create a staging subdomain such as
`staging.example-domain.co.za`, supplying the real domain only in cPanel and
its private environment. Staging needs a separate database, shared storage,
`.env`, `APP_KEY`, and cron entries.

The deployment script confirms production intent, validates repository and
environment placement, preserves shared storage, enables maintenance mode for
an installed application, installs locked production dependencies, repairs
permissions without `777`, links storage, clears stale caches, applies
forward-only migrations, validates cPanel prerequisites, optimizes Laravel,
restarts queue state, runs a secret-safe health diagnosis, and restores
service. Its exit trap attempts to leave maintenance mode after intermediate
failure.

## Mail and AI

Use authenticated SMTP from a cPanel mailbox or an external transactional
provider. Do not prefer PHP `mail()`. Configure a verified sender, then check
SPF, DKIM, and DMARC in cPanel Email Deliverability and the external DNS
provider. `platform:validate-environment --cpanel` is the non-sending mail
configuration diagnostic.

Do not run Ollama or other model inference on shared hosting. Enable only a
hosted provider and set its private key in the shared `.env`. Keep concurrency
low and monitor slow/failed AI requests.

## Storage and resource limits

Back up both MariaDB and all of `sky-fundi-shared/storage`. Keep public uploads
under `storage/app/public` and learner documents on the private local disk.
Configure cPanel/PHP `upload_max_filesize` and `post_max_size` at or below
25 MB initially, and enforce conservative per-organisation allowances through
the existing licensing/settings workflow. Do not host unrestricted video or
textbook libraries on the 25 GB account.

With one CPU and 2 GB RAM:

- run only the bounded queue cron;
- paginate interactive tables and chunk approved bulk operations;
- stream large transfers where existing workflows support it;
- avoid simultaneous AI plan generation and large imports;
- keep queue memory at 192 MB and timeout at 50 seconds;
- monitor entry-process limits, disk usage, slow requests, logs, and failed jobs.

## Health, backups, and rollback

After deployment verify:

```text
https://YOUR_DOMAIN/up
https://YOUR_DOMAIN/health
https://YOUR_DOMAIN/ready
```

Responses expose component status only, not credentials, paths, provider
responses, or traces. Readiness checks local configuration/dependencies and
does not contact external AI providers.

Enable cPanel daily account/database backups. Before a high-risk migration,
export MariaDB through cPanel/phpMyAdmin or `mysqldump`, and archive the shared
storage outside the web root. Copy backups off-account and test restoration.

Rollback checklist:

1. Enable maintenance mode.
2. Record the failed release and database migration state.
3. Check out the previously reviewed Git commit.
4. Run `composer install --no-dev --prefer-dist --optimize-autoloader`.
5. Run `php artisan optimize`.
6. Restore service and verify `/ready`.

Production migrations are normally forward-only. The deployment script never
runs `migrate:rollback`. If a release requires reversing data/schema changes,
use a reviewed forward corrective migration or restore a verified database and
matching storage backup during an explicitly approved maintenance window.

## Final cPanel checklist

- PHP 8.3 and required extensions selected.
- Domain document root points to `current/public`; AutoSSL is valid.
- Private `.env` is mode 600 and contains no placeholders.
- MariaDB user is restricted to the application database.
- Shared storage is linked, writable, persistent, and backed up.
- Queue and scheduler cron entries use the correct PHP binary.
- SMTP sender and DNS authentication are verified.
- `/up`, `/health`, and `/ready` are safe and successful.
- Sensitive URL probes return 403/404.
- No credentials, database exports, logs, or uploaded files are tracked by Git.
