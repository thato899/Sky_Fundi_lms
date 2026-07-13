# Organizations

The Organizations module is Sky Fundi's tenancy foundation. It represents an organisation without assuming that the organisation is a school: type values are configuration-driven and may be extended in `config/organizations.php`.

## Scope

`Organization` holds identity, contact and address data, entitlement limits, licensing metadata, usage counters, lifecycle status, and audit actors. Its UUID is the tenancy boundary that future institution modules must reference. It does not introduce teachers, learners, or education-operation data.

The module stores per-organisation settings grouped by concern (academic, branding, notifications, security, storage, AI, email, and general regional preferences). Branding uses a strict hierarchy: organisation values override Core Branding values; omitted values automatically inherit the platform defaults. AI credentials are encrypted at rest and are configuration only—the existing Core AI Gateway remains the only provider integration path.

Administrators are assigned through `organization_administrators`; the policy checks that membership, preventing an administrator from accessing a different organisation. Platform users with `organizations.*` permissions are the super-admin control plane.

## API

All endpoints are authenticated under `/api/v1/organizations`. The list supports `search`, `status`, `type`, `sort`, `direction`, and `per_page`. Lifecycle, settings, branding, AI configuration, administrator assignment, and per-organisation module assignment are separate endpoints in `routes/api.php`.

Run `php artisan db:seed --class="Modules\\Organizations\\Database\\Seeders\\OrganizationsDatabaseSeeder"` after migration to register permissions. Install/enable registered modules before assigning them to an organization; assignment delegates to Core Module Manager.
