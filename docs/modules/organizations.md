# Organization Management

Organizations are the tenancy boundary for Sky Fundi clients. `organizations.id` is a UUID and future tenant-owned tables must include `organization_id`, a foreign key, and an organization scope. Types are configured in `modules/Organizations/config/organizations.php`; adding a type is a configuration change, not a schema or code fork.

## API and access

The module exposes authenticated REST endpoints at `/api/v1/organizations`. `GET /` supports search, status/type filters, pagination, and safe sorting. An organization administrator is restricted to organizations listed in `organization_administrators`; platform users with `organizations.manage` are the unrestricted super-admin control plane. The module registers view/manage, branding, AI, modules, users, settings, and security permissions through its seeder.

## Settings and branding

Settings use `organization_settings` with a group/key/value shape so new concerns do not need migrations. General values seed from module configuration. Branding resolves organization overrides first and delegates all missing fields to Core Branding platform defaults. This is intentionally inheritance, not a copied branding record.

## Licensing, modules, and AI

License, renewal, entitlement limit, and support-plan metadata live on the organization. `organization_modules` records assignment and delegates enablement to Core Module Manager; it does not implement the assigned modules. The AI configuration stores provider options and encrypted credentials only. AI requests must use Core AIGateway; this module never invokes providers directly.

## Security and audit

All lifecycle/configuration changes emit Auditable organization events. The organization boundary is compatible with Core Security features such as trusted devices, IP restrictions, session limits, alerts, and suspicious-login tracking; organization-specific security policies are stored under the `security` settings group rather than duplicating Core Security state.
