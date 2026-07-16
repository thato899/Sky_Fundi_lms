# Environment variables

`.env.example` is the authoritative safe key inventory. The `init` service copies it to `.env` only when `.env` is absent and generates a missing application key. Never commit or print `.env` values.

Implemented categories include Laravel application/debug/URL/key, logging, MySQL, session/cache/queue/Redis, mail, filesystem/S3, Sanctum, backup, health, installer, branding, and AI provider configuration. `config/*.php` reads environment values; application code uses `config()`.

AI selection and credentials may also be stored per organization through encrypted organization AI configuration, but all calls still pass through Core AIGateway. Platform Settings are database-backed global runtime settings. Redis is an optional Compose profile; the local default queue is database. Production operators must inject secrets, configure TLS/cookies/proxies, durable storage, real mail, workers, scheduler, logging, and backups outside source control.
