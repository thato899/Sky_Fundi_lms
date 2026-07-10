# Architecture Overview

## Purpose

Sky Fundi replaces a legacy, monolithic, tightly-coupled education system. The previous system failed because educational features, platform infrastructure, and institution-specific customizations were all mixed together, making the codebase fragile and difficult to extend safely.

The Sky Fundi architecture exists to guarantee one property above all others:

> **Adding, changing, or removing a feature for one type of institution must never risk breaking the platform, or any other feature, for a different institution.**

## Guiding Principles

The platform is built on:

- **SOLID Principles** — each class has one reason to change; behavior is extended via composition and interfaces, not modification of existing code.
- **Clean Architecture** — dependencies point inward, toward domain logic, never outward toward frameworks or infrastructure. See [`clean-architecture.md`](clean-architecture.md).
- **Domain-Driven Design (where appropriate)** — each module owns a bounded context with its own ubiquitous language, entities, and rules. DDD is applied pragmatically; not every module needs full tactical DDD patterns, but every module must have a clear domain boundary.
- **Modular Architecture** — see [`module-system.md`](module-system.md).
- **Service Layer Pattern** — business logic lives in services, not controllers or Eloquent models.
- **Repository Pattern (where useful)** — used to decouple persistence from domain/service logic, particularly where a module's data access needs to be swappable or heavily tested in isolation. Not mandated for every trivial CRUD case.
- **Dependency Injection** — constructor injection via the Laravel service container throughout; no static facades in domain/service code.
- **PSR Standards** — PSR-4 autoloading, PSR-12 coding style.
- **REST API First** — every capability is designed as an API before any UI consumes it.
- **Secure by Default** — authentication, authorization, and audit logging are Core concerns present from day one, not retrofitted.
- **Multi-Tenant Ready** — the domain model is tenant-aware even where a given deployment is single-tenant per database.
- **Mobile API Ready** — the API is designed for consumption by web, Flutter, and native mobile clients equally; no server-rendered-only assumptions leak into the API layer.
- **AI Ready** — AI capability is available to every module through a single gateway abstraction.

## High-Level System Shape

```
┌─────────────────────────────────────────────────────────────┐
│                         Clients                                │
│   Blade (web)   │   React (future)  │  Flutter / Android      │
└──────────────────────────────┬────────────────────────────────┘
                                 │ REST API (versioned)
┌────────────────────────────────▼───────────────────────────────┐
│                        API Layer (core/Api)                     │
│         Routing · Request validation · Response shaping         │
└────────────────────────────────┬───────────────────────────────┘
                                 │
┌────────────────────────────────▼───────────────────────────────┐
│                             MODULES                              │
│  Academics · Attendance · Homework · Assessments · Library ·    │
│  Sports · Transport · Finance · Messaging · Reports · ...       │
│  (each module: own domain, services, repositories, migrations)  │
└────────────────────────────────┬───────────────────────────────┘
                                 │ uses (never bypasses)
┌────────────────────────────────▼───────────────────────────────┐
│                          PLATFORM CORE                           │
│  Auth · RBAC · Users · Branding · Settings · Notifications ·    │
│  Audit Logs · Storage · Billing · Licensing · AI Gateway ·       │
│  Logging · File Management                                      │
└──────────────────────────────────────────────────────────────┘
```

Modules depend on Core. Core never depends on a module. Modules do not depend on each other directly — see [`module-system.md`](module-system.md) for how cross-module communication is handled.

## Why This Shape

- **Core stays small and stable.** Because Core contains no educational concepts, it changes rarely, which means the riskiest, most security-sensitive part of the platform (auth, billing, RBAC) is also the most stable.
- **Modules can be installed per tenant type.** A tutoring centre doesn't need a Hostel or Transport module; a boarding school does. Because modules are isolated, tenants only run what they need.
- **New institution types are additive, not disruptive.** Supporting a "Training Academy" tenant type in the future should mean writing new modules and tenant configuration, not touching Core or existing modules.
- **AI can be adopted incrementally and safely.** Because all AI access goes through one gateway, provider changes, cost controls, and safety policies are enforced in one place rather than scattered across the codebase.

## Related Documents

- [Clean Architecture Layers](clean-architecture.md)
- [Module System](module-system.md)
- [Multi-Tenancy](multi-tenancy.md)
- [Module Development Guide](../modules/module-development-guide.md)
- [AI Gateway](../ai/ai-gateway.md)
- [Security Overview](../security/README.md)
