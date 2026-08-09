# Codex delivery protocol

This file complements [AGENTS.md](AGENTS.md). It is the persistent delivery contract for Codex-led work on Sky Fundi: read both files before a phase begins.

## The rule: every phase leaves the map better than it found it

Before implementation, update or create the phase entry in [the delivery ledger](docs/planning/DELIVERY_LEDGER.md). At phase handoff, update that same entry with the shipped behaviour, changed contracts, migrations, verification evidence, deployment notes, and deliberately deferred work. Do not describe a planned capability as shipped.

The ledger is the short operational history. Module READMEs remain the source for owned behaviour; ADRs record architectural decisions; `status.md` and `plan.md` retain broader narrative and priorities. Link rather than copy information across them.

## Deployable-stage contract

Each stage must be independently deployable and reversible:

1. Start from current `main` on a task branch; one coherent outcome per branch.
2. Define user outcome, non-goals, tenant/authorization constraints, UI states, migrations, and rollback before coding.
3. Preserve compatibility with the previous database/application version wherever a rolling deployment requires it.
4. Prefer additive migrations, guarded feature exposure, and safe defaults. A migration must have a tested `down()` path unless an ADR explains why that is impossible.
5. Build the smallest end-to-end vertical slice: policy, service, request/resource, route, accessible Blade experience, tests, and operational documentation as applicable.
6. Design UI for real workflows: responsive hierarchy, keyboard access, visible status and errors, meaningful empty/loading states, destructive-action confirmation, and organization-safe context.
7. Run proportional verification and record exact results. Never substitute a claim, screenshot, or static review for a command that did not run.
8. Update the ledger and relevant README/runbook, review the diff, then publish only when expressly authorized.

## Phase documentation template

Every delivery-ledger entry must include:

- **Outcome:** one sentence from the user’s perspective.
- **Scope / non-goals:** clear boundaries to prevent accidental product expansion.
- **UX contract:** primary journey, roles, permissions, responsive/accessibility expectations, and empty/error states.
- **Technical contract:** owning module/core service, routes/API changes, jobs/events, configuration, data ownership, and migration/rollback plan.
- **Deployment gate:** required environment/configuration and the exact command sequence for a safe release.
- **Verification evidence:** commands actually run and exact results.
- **Follow-ups:** concrete next slices, risks, and decisions needed.

## Quality bar

UI polish is a product requirement, not a final styling pass. Reuse the existing Blade design system and tenant branding; do not introduce a parallel frontend stack without an ADR and explicit approval. Every externally visible workflow must protect organization boundaries and demonstrate a clear success path as well as safe failure handling.

## Current next phase

The active planned slice is **Learner invitation and onboarding**. It must mirror the secure guardian-invitation model only where the Learner module’s ownership, privacy, and portal permissions permit it. Its implementation begins after the Phase 1 entry in the ledger is reviewed and refined against the existing invitation services, policies, routes, and tests.
