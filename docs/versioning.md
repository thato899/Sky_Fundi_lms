# Versioning

## Platform Versioning

The platform follows **Semantic Versioning** (`MAJOR.MINOR.PATCH`) for releases tagged on `main` (see [Release Process](deployment/release-process.md)):

- **MAJOR** — breaking API changes, or breaking changes to the module manifest contract.
- **MINOR** — new modules, new Core capability, new API endpoints/fields — backward compatible.
- **PATCH** — bug fixes, no interface changes.

## API Versioning

The REST API is versioned independently in its URL path (`/api/v1/...`, see [API Conventions](api/conventions.md#versioning)). A platform MAJOR version bump does not automatically imply an API version bump, and vice versa — they're tracked separately because a platform release can ship many non-API changes (e.g. new internal Core capability) without breaking existing API consumers.

## Module Versioning

Each module has its own `version` in its manifest ([Module Manifest](architecture/module-system.md#module-manifest-modulejson)), also SemVer, tracked independently of the platform release version — see [Release Process — Module Version Independence](deployment/release-process.md#module-version-independence).

## Deprecation Policy

Breaking changes (API MAJOR bump, module manifest MAJOR bump) must be preceded by a documented deprecation notice with a defined support window for the outgoing version, once the platform has real external consumers (mobile apps, third-party integrations) that would be affected. Exact deprecation-window length will be fixed at that point.
