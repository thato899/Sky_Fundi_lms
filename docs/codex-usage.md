# How Codex was used to build Sky Fundi

Sky Fundi was built with an AI engineering agent ("Codex") working as a governed team member — not as an autocomplete. The repository itself defines how the agent must behave, every change went through the same gates a human contribution would, and a human reviewed and merged every pull request. This document explains the setup, the workflow, and what was delivered under it. (A PDF render of this document ships as `docs/codex-usage.pdf`.)

## 1. Governance before generation

The repository's [`AGENTS.md`](../AGENTS.md) is the agent's operating manual, versioned like code. It binds the agent to:

- **Scope control** — the smallest cohesive change; no speculative features, no drive-by refactoring.
- **Tenant safety** — every organization-owned row carries `organization_id`; cross-organization access must fail and be tested.
- **Process rules** — never implement on `main`; branch per task; conventional commits; draft PRs a human merges; never fabricate command output or test totals.
- **Security rules** — no secrets in the repo, no weakened tests, all AI-provider calls through the core AI Gateway only.

The operational side lives in [`docs/development/CODEX_WORKFLOW.md`](development/CODEX_WORKFLOW.md).

## 2. The working loop

Every feature followed the same loop, visible in the commit and PR history:

1. **Assess** — the agent read the module READMEs, migrations, neighboring code, and tests before proposing anything, and wrote its findings into `status.md` / `plan.md`.
2. **Design first** — schema-affecting work started as an Architecture Decision Record reviewed before implementation: [ADR-008 Historical enrolment](adr/008-historical-enrolment.md) and [ADR-009 Teaching assignments](adr/009-teaching-assignments.md) were both authored, reviewed, and accepted this way.
3. **Implement on a branch** — one task, one branch, conventional commits.
4. **Verify proportionally** — targeted tests, then the full suite, Laravel Pint, PHPStan level 5, and MySQL forward-migration checks; exact totals reported in every PR (the final state: **274 tests / 1,537 assertions**).
5. **Rehearse live** — beyond unit tests, the agent drove the running application over HTTP as each persona: a scripted 12-point end-to-end journey (teacher creates a quiz → learner writes it → AI-assisted marking → release → study plan → parent report → guardian portal) had to pass against the real stack before push.
6. **Human merge** — pull requests with CI green were reviewed and merged by a person. When GitHub's automatic "update branch" merges silently dropped two view files, the discipline caught it: CI failed, the agent diagnosed the lost hunks, and restored them in a reviewed fix commit.

## 3. What was delivered under this process

- **Historical enrolment** (ADR-008): date-ranged placement history with backfill and self-healing, making report-card history survive mid-year class moves.
- **Teaching assignments** (ADR-009): date-ranged teacher-to-class/subject assignments with per-organization opt-in enforcement across marking, attendance, and scheduling, plus an administrator bypass permission.
- **Persona experience**: role-aware post-login routing and navigation (learner → quizzes, guardian → portal, teacher → assessments), and a design-system refresh of the demo journey screens.
- **AI with teacher oversight**: AI-suggested marks with rationale and misconceptions that never become official without teacher approval; a teacher-authored **report to parent** attached to results; and a **deterministic study-plan fallback** so a released result always ships a plan even with no AI provider reachable.
- **Family visibility**: guardian portal, report-card publication notifications, and the learner's own published report-card page.
- **Platform hardening**: AI-gateway transport-error wrapping, UTF-8 scrubbing, audit records on every consequential action, and cross-organization isolation tests.

## 4. Why this matters

The point is not that an AI wrote code — it is that the AI worked inside an auditable engineering system: designs recorded as ADRs, tenancy and security rules enforced as written policy, every claim backed by runnable checks, and a human owning every merge. The same principle the product applies to AI marking — *AI suggests, the teacher decides* — governed how the product itself was built.
