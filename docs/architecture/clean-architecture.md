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
- Owns use-case orchestration and remains independent of HTTP presentation.
- Uses dependency injection. Existing simple services may use Infrastructure Eloquent models directly; repository interfaces are introduced where query complexity or substitutability justifies them.

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
3. Services use injected collaborators. Repositories/interfaces are used where useful; simple services may follow the established direct-Eloquent pattern.
4. Cross-cutting concerns (logging, audit, notifications) are invoked through Core service interfaces, never instantiated directly inside a module's domain/application code.
5. Every module and Core sub-package structures its own code using these same four layers — the pattern is fractal, not just applied once at the top level.

## Why Repository Pattern Is "Where Useful," Not Mandatory Everywhere

Domain code remains framework-independent where a bounded context has a distinct Domain layer. A repository is not mandatory for every Eloquent-backed service. Prefer one where query logic is non-trivial, an aggregate is shared across services, or persistence substitutability is a real requirement.
