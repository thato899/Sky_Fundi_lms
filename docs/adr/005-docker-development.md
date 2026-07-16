# ADR-005: Docker development

Status: Accepted

## Decision

Use Docker Compose as the canonical PHP development environment, with one-shot initialization, app, MySQL, Mailpit, queue, scheduler, shared storage, and optional Redis services.

## Consequences

Local commands are reproducible across Linux/WSL and exercise MySQL where required. The stack is development-oriented; `artisan serve`, Mailpit, bind-mounted source, and committed local credentials are not production architecture.
