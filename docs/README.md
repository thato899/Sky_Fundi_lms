# Documentation

This directory contains all architecture, development, and operations documentation for the Sky Fundi Platform. It is the single source of truth for how the platform is designed and how contributors should work within it.

## Structure

| Folder | Contents |
|---|---|
| [`architecture/`](architecture/) | System architecture, Clean Architecture layering, module system, multi-tenancy |
| [`modules/`](modules/) | How to design, build, and register a module |
| [`api/`](api/) | REST API conventions, authentication, error handling |
| [`database/`](database/) | Database and migration conventions |
| [`security/`](security/) | RBAC, policies, secrets, authentication security |
| [`deployment/`](deployment/) | Environments, release process, deployment strategy |
| [`development/`](development/) | Onboarding, coding standards, git workflow, testing strategy |
| [`mobile/`](mobile/) | Mobile API readiness (Flutter / Android) |
| [`ai/`](ai/) | AI Gateway abstraction and provider integration |
| [`ui/`](ui/) | Frontend/UI conventions (Blade today, React/Flutter future) |
| [`testing/`](testing/) | Test strategy and conventions detail |
| [`operations/`](operations/) | Runtime diagnosis, backup, queue, scheduler, and incident expectations |
| [`adr/`](adr/) | Accepted architecture decisions |

Root-level docs (`roadmap.md`, `versioning.md`, `naming-conventions.md`, `environment-variables.md`) apply platform-wide and don't fit a single subfolder.

Documentation is treated as a first-class deliverable: any PR that changes behavior described here must update the relevant document in the same PR.

The dated [documentation audit](documentation-audit.md) records the evidence reviewed, stale claims corrected, and remaining gaps.
