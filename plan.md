# Implementation Plan — Sky Fundi Platform

_Created: 2026-07-20. Companion to [status.md](status.md). Priorities derive from `docs/roadmap.md` ("Planned" items), module README non-goals, and the README's definition of the sellable education MVP._

## Guiding intent

The platform foundation and all 8 education modules work. The gap between "working platform" and "sellable education MVP" is: learner/guardian-facing value (portal, notifications), historical correctness (enrolment history), operational trust (deployment automation, tested restore, 2FA), and real billing. The plan closes those gaps in that order, with hardening woven in.

## Working method (non-negotiable, from AGENTS.md)

- One task = one branch off `main` (`<type>/<scope>-<summary>`); never implement on `main`; PRs via `scripts/publish-draft-pr.sh`; humans merge.
- Environment: `make init` → `make up`; run everything through the `app` container.
- Before handoff: targeted tests → `make test`, `make pint`, `make analyse`, `make migrate-check` when migrations changed, `composer validate --strict` when composer changed.
- Smallest cohesive change per PR; preserve module boundaries; all AI calls through `core/AIGateway`; every org-owned table gets an indexed `organization_id`.

## Phase 0 — Verify the baseline (½ day)

1. Bring the stack up (`make init && make up`, migrate + seed) and run `make verify` to confirm a green baseline before any new work.
2. **Quick doc fix:** correct the stale "no hosted CI" claim in `docs/documentation-audit.md` (CI workflows exist since PR #28+). One-line PR.

## Phase 1 — Historical enrolment/placement (foundation for everything downstream)

**Why first:** Attendance, Assessments, and Reports currently interpret history through *current* placement. Every feature built before this exists compounds the migration cost. It is also the roadmap's first planned item.

- Design an `enrolments` (learner ↔ class-group ↔ academic-year/term, date-ranged) model in **Learners** (owner) with an ADR; Academics stays upstream.
- Migrate current placements into an initial enrolment row (additive migration, complete `down()`).
- Switch Attendance/Assessments/Reports reads to resolve placement *as of the record's date*; keep write paths on active enrolment.
- Isolation + regression tests: cross-org association, history preserved across learner moves.
- **Estimated shape:** 3–4 PRs (schema+backfill, Learners API/UI, downstream read-path switches, docs).

## Phase 2 — Teacher/class/subject assignments + assignment-aware authorization

**Why now:** unblocks correct teacher scoping for quizzes, mark sheets, attendance, and lessons; Scheduling already stores staff-lesson assignments to build on.

- Add authoritative teacher→class/subject assignment (likely in Academics or a thin slice in Staff; decide via ADR against module boundaries).
- Layer assignment checks on top of existing permission middleware in Assessments, Attendance, and Scheduling policies.
- **Estimated shape:** 2–3 PRs.

## Phase 3 — Learner portal + results notifications (the sellable surface)

- Learner portal onboarding, reusing the proven guardian invitation pattern (hashed expiring tokens, queued email, new/existing-account acceptance, restricted access).
- Learner-facing views: own timetable, attendance summary, quiz attempts, published report cards.
- Results notifications on report-card publication via `core/Notifications` + `core/Mail` (event-driven, org-scoped, opt-out respected).
- **Estimated shape:** 3–4 PRs (portal auth/onboarding, learner views, notifications).

## Phase 4 — Operational trust

1. **Tested backup restore:** turn `scripts/restore-database-validation.sh` into a verified, documented restore workflow exercised in CI (isolated DB, like `migrate-check`).
2. **Deployment automation:** extend `deployment-validation.yml` into an actual deploy pipeline for the chosen target (Docker VPS primary; keep the cPanel runbook validated by its existing test).
3. **Enforced 2FA:** build on `core/Auth` + Security Centre (trusted-device test exists); org-level policy toggle via Settings.
- **Estimated shape:** 3 PRs, largely independent — can parallelize with Phases 1–3.

## Phase 5 — Hardening and debt (weave into slack time)

- **Test depth:** Attendance, Reports, Scheduling, and Staff each have a single test file. Add failure/authorization/isolation coverage to match Learners' standard (11 files).
- **Live adapters:** implement the Claude and Gemini providers in `core/AIGateway` (HTTP call is the only remaining work — placeholder classes already registered, `DeepSeekProviderTest` is the pattern). Same for any placeholder Storage/Mail/Notification adapters actually needed by deployments.
- **Module registry:** make enable/disable actually gate provider registration, or document it as install-time-only.
- **Stub cores:** implement `core/Billing` only when Phase 6 starts; delete-or-justify `core/Events` and `core/FileManagement` READMEs if nothing will land there soon (AGENTS.md forbids empty placeholders).

## Phase 6 — Real billing (replaces hackathon demo)

- Payment gateway integration behind `core/Billing`, invoices, and subscription lifecycle wired to the existing `core/Subscriptions` + `core/Licensing` foundations; replace `HackathonSubscriptionController`.
- Gate: needs a product decision on gateway/provider and pricing — **confirm with stakeholders before starting**.

## Explicitly deferred (roadmap "future ideas" — do not start without a decision)

Homework/content delivery, online examinations, promotion/progression/rankings, staff attendance, mobile/offline clients, calendar/conferencing sync, timetable optimization, and the library/transport/finance/messaging/sports/clinic/hostel/visitors/inventory modules.

## Suggested immediate next steps

1. Phase 0 baseline verification + the one-line doc fix.
2. Write the historical-enrolment ADR (Phase 1 design) and get it reviewed before any schema work.
3. In parallel, start Phase 5 test-depth work on Attendance/Reports/Scheduling/Staff — low-risk, high-value, no design decisions needed.
