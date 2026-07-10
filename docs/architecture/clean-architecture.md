# Clean Architecture Layers

Sky Fundi organizes code, within both Core and each module, into four concentric layers. Dependencies only point **inward**. Nothing in an inner layer may reference an outer layer.

```
┌────────────────────────────────────────────┐
│  Infrastructure  (Eloquent, HTTP, Queue,     │
│  external APIs, filesystem, AI providers)    │  outermost
│  ┌────────────────────────────────────────┐ │
│  │  Interface / Adapters                    │ │
│  │  (Controllers, Form Requests, API         │ │
│  │  Resources, Console Commands, Repository  │ │
│  │  implementations)                          │ │
│  │  ┌──────────────────────────────────────┐│ │
│  │  │  Application / Service Layer            ││ │
│  │  │  (Use cases, Services, orchestration,   ││ │
│  │  │  DTOs, events)                           ││ │
│  │  │  ┌────────────────────────────────────┐││ │
│  │  │  │  Domain                              │││ │
│  │  │  │  (Entities, Value Objects, domain     │││ │
│  │  │  │  rules, repository interfaces)         │││ │
│  │  │  └────────────────────────────────────┘││ │
│  │  └──────────────────────────────────────┘│ │
│  └────────────────────────────────────────┘ │
└────────────────────────────────────────────┘
```

## Layer Responsibilities

### Domain
- Pure PHP. No Laravel, no Eloquent, no HTTP.
- Entities and value objects that encode business rules (e.g. what makes a `TuitionFee` valid).
- Repository **interfaces** live here (the contract), not their implementation.
- No dependency on any framework class.

### Application / Service Layer
- Use cases / services that orchestrate domain objects to fulfill a request (e.g. `EnrollLearnerService`).
- Depends only on Domain (and abstractions it defines).
- Emits domain events; has no knowledge of HTTP, queues, or specific persistence technology.

### Interface / Adapters
- Controllers, Form Requests, API Resources/Transformers, Console Commands.
- Eloquent-backed repository **implementations** that satisfy Domain interfaces.
- Translates between the outside world (HTTP requests, CLI, queue payloads) and the Application layer.

### Infrastructure
- Framework and third-party integration: Eloquent models and migrations, queue drivers, mail drivers, AI provider SDK clients (used only inside the AI Gateway — see [`../ai/ai-gateway.md`](../ai/ai-gateway.md)), filesystem drivers.
- Swappable without touching Domain or Application code.

## Practical Rules

1. Controllers must be thin: validate input (via Form Requests), call a Service, return a Response/Resource. No business logic in controllers.
2. Eloquent models are Infrastructure. Domain logic does not live in Eloquent model methods beyond simple relationships/casts.
3. Services depend on repository **interfaces**, injected via the container; the concrete Eloquent repository is bound in a module's or Core's Service Provider.
4. Cross-cutting concerns (logging, audit, notifications) are invoked through Core service interfaces, never instantiated directly inside a module's domain/application code.
5. Every module and Core sub-package structures its own code using these same four layers — the pattern is fractal, not just applied once at the top level.

## Why Repository Pattern Is "Where Useful," Not Mandatory Everywhere

Clean Architecture requires that Domain not depend on Infrastructure — that's non-negotiable, and is achieved via repository *interfaces*. Whether a given entity needs a full custom repository implementation (versus a thin wrapper around Eloquent) is a per-case decision documented in [`../database/conventions.md`](../database/conventions.md). Prefer repositories where: query logic is non-trivial, the same aggregate is read/written from multiple services, or the module anticipates swapping storage technology (e.g. a reporting module reading from a data warehouse instead of MySQL).
