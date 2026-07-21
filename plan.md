# Plan — Sky Fundi Platform

_Updated: 2026-07-21 evening. **The hackathon sprint shipped and everything through PR #45 is merged to `main`.** Active priority: roadmap item 2 slice (a) — learner invitations/onboarding — plus a small infra-fix PR (see below). The sprint plan is preserved for history._

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
- **Hackathon sprint items 1–6 — done**: PR #38 (stacked on #37). LMS running and seeded on :8001; persona-aware routing + navigation (learner/teacher/guardian/admin all land correctly, verified live); UI overhaul of layout, quiz attempt/result, teacher review, guardian portal; subscription page reframed as profitability (validated bar palette). Remaining before the demo: merge PRs #37 → #38 (human or explicit authorization), rehearse the 5–7 minute script in `docs/hackathon-demo.md`, and optionally set an OpenAI key for live AI marking (falls back to seeded results without it).

## Immediate housekeeping (2026-07-21)

- **Infra-fix PR #47 (open):** three fixes. (1) `.dockerignore` now excludes the dangling `public/storage` symlink (Docker builds were failing with `invalid file request public/storage` on Windows hosts). (2) `docker/init.sh`'s APP_KEY guard is corrected from BRE `.+` (literal `+`) to `..*` — the old guard regenerated APP_KEY on every init rerun, logging out all sessions and orphaning encrypted values. (3) The Learners/Assessments provider migration-path logic from `8ef5ac2` is corrected — it preferred `Database/` whenever present, which on case-sensitive checkouts (CI) loaded no migrations and turned `main`'s CI red; the fix tests for the migrations directory itself. Verified against a full stack rebuild and a case-sensitive layout in the sf_test rig.

## Post-hackathon roadmap (now the active track)

1. ~~**Teacher/class/subject assignments**~~ **Done — implemented in PR #41** (ADR-009 accepted): schema, `TeachingAssignmentService`, opt-in enforcement across Assessments/Attendance/Scheduling, actor-level teacher gating with admin bypass, seeded demo assignment. Merge order: #40 → #39 → #41. Follow-up (roadmap): assignment web/API administration and bulk tooling.
2. **Learner portal onboarding + results notifications** — slices (b) and (c) **done and merged (PR #44)**: report-card publication notifications to learner + guardians, and the learner's `My report cards` portal page with nav link. **Remaining: slice (a)** — learner invitation/onboarding service + acceptance flow mirroring `GuardianInvitationService` (token table, queued mail, acceptance controller/views, permissions, tests) — the next task. Optional later portal surfaces: attendance summary and timetable pages.
   **Also shipped en route** (merged via #43): teacher parent reports on study plans, Ollama transport-error wrapping, and the deterministic study-plan fallback. The consolidated docs PR is also merged (#45). **All planned merges are complete.**
3. **Operational trust:** tested backup restore in CI, deployment automation, enforced 2FA.
4. **Hardening:** test depth for Attendance/Reports/Scheduling/Staff; live Claude/Gemini AI adapters; module-registry enable/disable semantics; delete-or-justify stub cores (Billing/Events/FileManagement).
5. **Real billing** behind `core/Billing` (needs a gateway/pricing decision).
6. Deferred: homework/content, online exams, promotion/rankings, staff attendance, mobile/offline, integrations, additional modules.

## Working method (from AGENTS.md — unchanged)

Branch per task off `main`; never implement on `main`; draft PRs via the publishing flow; humans merge (or explicitly authorize a merge). Proportional verification before handoff: targeted tests → `make test`, `make pint`, `make analyse`, `make migrate-check` when migrations change.
