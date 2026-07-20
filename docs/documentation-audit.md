# Documentation alignment audit — 2026-07-16

## Method

The audit inspected the repository file inventory; registered providers; Composer mappings; all Core/module routes, migrations, models, Application services, middleware, policies, events, commands, tests, configuration, Make/scripts, Compose services, and existing Markdown. Runtime routes and Compose state were verified from the running containers. Implementation remains authoritative over prose.

## Corrected issues

- Replaced roadmap claims that educational modules/APIs and Academics ownership were future work.
- Replaced stale `develop` branch/PR instructions with the implemented `main` workflow.
- Replaced database-per-tenant, `tenant_id`, integer-ID, and universal-global-scope claims with shared-database `organization_id`, UUIDs, context middleware, scoped resolvers, and composite constraints.
- Distinguished registered placeholder AI/storage/mail/notification adapters from live implementations.
- Documented the actual Compose services, ports, queue worker, scheduler, optional Redis profile, volumes, and production limitations.
- Corrected testing docs that were only placeholders and explicitly recorded that hosted GitHub Actions workflows are absent.
- Documented runtime provider activation separately from module registry state.
- Documented actual middleware order, policies, request lifecycle, audit strategy, CSRF, validation, mass assignment, UUID resolution, queue workload, schedule frequencies, storage, configuration, backup/restore limitation, and health surfaces.
- Reorganized the roadmap into Completed, In progress, Planned, and Future ideas.
- Added missing operations documentation and seven decision records.

## Remaining gaps

- Endpoint request schemas remain executable in Form Request/controller code; maintaining a second field-by-field OpenAPI description would drift without generation/contract tests. Module API guides cover the public operational contracts and the API index points every surface to its authoritative source.
- No screenshots were present in the audited documentation, so no outdated image assets required removal.
- There is no production infrastructure-as-code, automated restore, or formal security-disclosure contact to document as implemented.
- Some older Core READMEs describe foundations and placeholders in more detail; their explicit limitations remain relevant, but future behavior should continue to be verified against code.

## Post-audit update — 2026-07-20

Hosted GitHub Actions workflows now exist: `.github/workflows/ci.yml` (composer validation, migration check, tests, Pint, PHPStan on every push and pull request) and `.github/workflows/deployment-validation.yml` (deployment-artifact validation). They were added after this audit's date, so the original recording of their absence was accurate at audit time and is superseded here rather than rewritten.
