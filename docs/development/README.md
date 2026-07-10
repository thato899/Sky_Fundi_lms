# Development Documentation

- [`onboarding.md`](onboarding.md) — getting a working environment as a new contributor
- [`coding-standards.md`](coding-standards.md) — PHP/Laravel coding standards
- [`git-workflow.md`](git-workflow.md) — branch strategy, commit conventions
- [`testing-strategy.md`](testing-strategy.md) — how modules and Core are tested

## Logging Strategy

Application/error logging (as opposed to security audit logging, see [Security](../security/README.md)) goes through `core/Logging`, a thin wrapper around Laravel's logging with mandatory structured context (tenant ID, module, request/correlation ID) on every log line, so logs are filterable and traceable across a multi-tenant, modular system. Log levels follow standard PSR-3 severities; `debug`/`info` are never used for anything security-sensitive (see [Security Policies](../security/policies.md) on never logging plaintext credentials).
