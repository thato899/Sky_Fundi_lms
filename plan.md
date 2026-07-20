# Plan — Sky Fundi Platform

_Updated: 2026-07-20 evening. **Active priority: hackathon demo, deadline midnight tonight.** The long-term phased roadmap is preserved below the sprint plan._

## Tonight's hackathon sprint (in priority order, per product direction)

**Goal:** a running, polished, demo-ready LMS showing the core user workflows — learner and teacher first, then guardian — plus a finances/profitability view.

1. **LMS runs.** Docker stack up (`init` completed; app/queue/scheduler/Mailpit/MySQL), database migrated and seeded, `HackathonDemoSeeder` loaded, all demo-path pages return 200. This is the gate for everything else.
2. **Learner workflow.** Login → see assigned quiz → take quiz → deterministic + AI-suggested marks → released results → adaptive study plan. Smooth, obvious, no dead ends.
3. **Teacher workflow.** Login → create/populate assessment/quiz → capture or approve AI-suggested marks → release → intervention/risk dashboard.
4. **Guardian workflow.** Invited guardian → portal → linked learner's progress (restricted view).
5. **UI/UX overhaul.** The single Blade layout and the demo-path screens (entry, login, dashboards, quiz flow, subscription) get a coherent, modern visual identity: navigation, typography, color system, cards, empty states. Server-rendered CSS only — no build pipeline.
6. **Finances/profitability.** Extend the `/subscription` hackathon billing surface into a clear profitability story: revenue per seat vs AI/infra cost, margin per organization.
7. **Final verification and push.** Targeted tests + full suite, commit, push each finished chunk.

**Demo environment notes (Windows host):** shell scripts must be LF (fixed locally via `core.autocrlf input`); the baked Docker image collapses `Database/` vs `database/` module folders on Windows checkouts — use the bind-mounted dev stack (unaffected) for the demo; a standalone `sf_test` container (image + symlinks) is the fast test rig.

## Shipped today

- **Phase 0** — status/plan docs + CI-claim correction: PR #36 (draft).
- **Phase 1 — historical enrolment**: `learner_enrolments` history table, backfill, transactional timeline maintenance in `LearnerService`, self-healing for pre-tracking learners, enrolment-aware report-card calculation, ADR-008: PR #37 (draft). Full suite 267 tests / 1488 assertions green; Pint and PHPStan clean on changed paths; MySQL forward migration verified in the dev stack.

## Post-hackathon roadmap (unchanged priorities, resume after tonight)

1. **Teacher/class/subject assignments** + assignment-aware authorization in Assessments, Attendance, Scheduling.
2. **Learner portal onboarding + results notifications** (reuse guardian invitation pattern; notifications on report publication).
3. **Operational trust:** tested backup restore in CI, deployment automation, enforced 2FA.
4. **Hardening:** test depth for Attendance/Reports/Scheduling/Staff; live Claude/Gemini AI adapters; module-registry enable/disable semantics; delete-or-justify stub cores (Billing/Events/FileManagement).
5. **Real billing** behind `core/Billing` (needs a gateway/pricing decision).
6. Deferred: homework/content, online exams, promotion/rankings, staff attendance, mobile/offline, integrations, additional modules.

## Working method (from AGENTS.md — unchanged)

Branch per task off `main`; never implement on `main`; draft PRs via the publishing flow; humans merge (or explicitly authorize a merge). Proportional verification before handoff: targeted tests → `make test`, `make pint`, `make analyse`, `make migrate-check` when migrations change.
