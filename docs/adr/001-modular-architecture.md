# ADR-001: Modular architecture

Status: Accepted

## Decision

Keep platform-wide concerns in `core/`, educational/operational bounded contexts in `modules/`, and boot both through explicit Laravel providers. Composer maps namespaces directly to these folders. Module manifests describe registry metadata and dependencies; they do not currently perform dynamic code loading.

## Consequences

Ownership, migrations, routes, services, and tests remain close together. Cross-module contracts must be explicit, dependency order matters, and provider registration—not registry state—is the current runtime activation mechanism.
