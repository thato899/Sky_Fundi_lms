# Project Status — Sky Fundi Platform

_Last updated: 2026-07-20 evening (baseline analysis as of commit `c9c6a78` on `main`)_

## Today's progress (2026-07-20)

- **Phase 0 shipped** — this status/plan pair plus the stale-CI-claim doc fix: draft PR #36.
- **Phase 1 shipped — historical enrolment**: date-ranged `learner_enrolments` history maintained transactionally on every placement write, backfilled from current placement, self-healing for learners created before tracking, and enrolment-aware report-card calculation (mid-period class moves no longer hide results). ADR-008. Draft PR #37. Verified: full suite **267 tests / 1488 assertions OK**, Pint and PHPStan clean on changed paths, MySQL forward migration ran in the dev stack.
- **Dev environment on Windows fixed and running**: CRLF shell scripts broke the container entrypoint (fixed via `core.autocrlf input` + LF normalization); Composer bind-mount timeout fixed with a raised timeout; full stack (app, MySQL, Mailpit, queue, scheduler) initialized, migrated, and seeded.
- **Priority pivot**: hackathon demo due midnight — learner and teacher workflows first, then guardians; LMS must run; UI/UX and a finances/profitability view are key. See [plan.md](plan.md).
- **Hackathon sprint shipped (PR #38, stacked on #37)**: persona-aware post-login routing and permission-driven navigation with a persona chip (learner → My quizzes, guardian → their portal, teacher → Assessments instead of a 403, admin → dashboard); design-system refresh in the root layout; redesigned quiz attempt/result, teacher review with AI-suggestion callouts, guardian portal, and the subscription page reframed as a profitability story (CVD-validated revenue-vs-cost bars, capacity meters). Full suite **267 tests / 1488 assertions OK**; Pint + PHPStan clean on changed files; all four demo personas verified live against the running stack.
- **Demo environment is live**: http://localhost:8001 (port 8000 was taken by an unrelated local process; a git-excluded local `compose.override.yaml` maps 8001). Demo password is set locally in `.env` (`HACKATHON_DEMO_PASSWORD`); demo logins are `admin@` / `math.teacher@` / `lerato@` / `thandi@ubuntu-future.demo`. Mailpit at http://localhost:8025.
- **Merge order for the demo branch history**: PR #36 (docs) any time; PR #37 (enrolment) into main; then PR #38 (demo experience, based on #37's branch); then PR #39 (ADR-009, based on #38's branch).
- **Session close-out (late evening)**: all PRs marked ready-for-review with green CI. Merging was blocked by the local permission system, so the merges are the one remaining human action. Full demo rehearsal passed live: 14/14 page/content checks across all four personas plus access-isolation checks (guardian denied on admin surfaces; unrelated guardian sees no learner data). Next task designed and opened for review: **ADR-009 teaching assignments** (PR #39, proposed) — date-ranged `staff_teaching_assignments` in Staff, assignment-aware authorization in Assessments/Attendance/Scheduling behind a per-organization opt-in.

## What this project is

Sky Fundi is a modular, multi-tenant education platform (LMS) for tutors, schools, colleges, and training providers. It is a **Laravel 12 / PHP 8.3** application organized as clean-architecture bounded contexts: platform-wide services in `core/`, educational domain modules in `modules/`, a thin Blade web host in `app/`. Tenancy is shared-database with `organization_id` ownership and UUID primary keys. The architecture is API-first: every module exposes a versioned `/api/v1` REST surface, and the server-rendered Blade UI consumes it. There is **no JS framework** (no Livewire/Inertia/Vite/package.json) — 77 Blade views on a single layout.

## Repository health

- Worktree: clean; branch `main`; no unmerged remote branches (`feature/domains-co-za-deployment` is fully merged).
- CI: GitHub Actions run on every push/PR (`ci.yml`: composer validate, migrate-check, tests, Pint, PHPStan) plus `deployment-validation.yml` for deployment artifacts.
- No TODO/FIXME/HACK markers in PHP code — deferred work is tracked in READMEs and `docs/roadmap.md` instead.
- Governance: `AGENTS.md` is the operating manual for all AI-assisted changes (branch-per-task, never implement on `main`, `make verify` before handoff).

## What is implemented

### Domain modules (`modules/`) — all 8 functional

| Module | Size | Scope |
|---|---|---|
| Academics | 103 files | Curricula, departments, years, terms, grades, classes, subjects, calendar, timetable periods. The upstream engine for everything else. |
| Learners | 68 files | Learner admin, learner numbering (row-locked sequences), status lifecycle, placement, **guardian management + invitation/portal onboarding** (hashed 7-day tokens, queued email), license-based capacity. |
| Assessments | 49 files | Categories, assessments, atomic mark sheets, gradebooks, CSV export, plus the **AI quiz slice**: questions, learner attempts, deterministic objective marking, AI written-answer suggestions with teacher approval, adaptive study plans, intervention/risk dashboard. |
| Organizations | 39 files | Tenancy foundation: org settings, encrypted per-org AI config, module assignment, administrators. API-only (Super Admin UI lives in `app/`). |
| Reports | 27 files | Grading scales/bands, reporting periods, display templates, versioned report-card snapshots with lifecycle (generated→approved→published→withdrawn), PDF (dompdf), formula-safe CSV. |
| Scheduling | 22 files | Rooms, weekly timetable templates, lesson materialization (≤93-day, idempotent), staff assignments, conflict detection, immutable change logs, attendance integration. |
| Attendance | 21 files | Session lifecycle (draft→open→finalized, audited reopen), preserved register entries, factual summaries, CSV, lesson linkage. |
| Staff | 14 files | Org-scoped staff profiles linked to Identity memberships, directory web+API. Document/invitation features are foundations only. |

### Core platform (`core/`)

- **Implemented (24 services):** AIGateway, Analytics, Api, AuditLogs, Auth (Sanctum), Backup, Branding, Deployment (profiles), FeatureFlags, Health, Identity (org context/memberships), Installer, Licensing, Mail, Modules (registry), Notifications, RBAC, Scheduler, Security, Settings, Storage, Subscriptions, Support, Users.
- **Partial (2):** Logging (single `PlatformLogger` class), Queue (enum only; processing lives in Health/Scheduler).
- **Stubs — README only, no code (3):** Billing, Events, FileManagement.

### AI Gateway

All AI access goes through `Core\AIGateway\Application\AIManager`; modules never call provider SDKs. Provider resolution: request preference → tenant default → platform default (`ollama`). Five registered providers:

- **Live:** Ollama (default, local), DeepSeek (incl. streaming/structured output), OpenAI (used by the AI-marking demo; falls back to deterministic marks when unavailable).
- **Intentional placeholders (throw `ProviderNotAvailableException`):** Claude, Gemini.

### Web UI surface

Public `/` and `/login`; org-admin `/dashboard`; `/subscription` (hackathon billing card); **Super Admin** area at `/super-admin` (dashboard, org creation wizard, users, roles, modules, AI config, audit, health); per-module management UIs at `/academics`, `/learners`, `/guardians`, `/staff`, `/assessments`, `/quizzes`, `/attendance`, `/reports`, `/scheduling`.

### Deployment

- **Docker (primary):** dev/staging/production compose files, Caddy, entrypoint/init scripts, env templates.
- **cPanel (secondary):** full runbook (`docs/cpanel-deployment.md`), `.env.cpanel.example`, `scripts/deploy-cpanel.sh`, and a dedicated feature test.
- **Tooling:** `scripts/` has deploy, validate, backup, restore-validation, health, and migrate-check scripts. Deploy pipeline is **not yet automated end-to-end**, and backup **restore is not yet a tested workflow** (roadmap: planned).

### Tests

Two PHPUnit suites (Unit, Feature) over SQLite `:memory:`. ~23 platform test classes in `tests/` (API contracts, auth, deployment, health/observability, RBAC, security, integration journeys) + 31 module test files. Coverage is **uneven across modules**: Learners 11, Academics 8, Assessments 4, Organizations 4 — but Attendance, Reports, Scheduling, and Staff have only **1 test file each**.

## Known gaps and limitations

1. **Not yet the sellable MVP** (per README): learner portal, staff attendance, historical enrolment, promotion/progression, rankings, online examinations, content delivery, real billing workflows, results notifications, and mobile/offline are out of scope so far.
2. **Historical enrolment missing** — attendance, assessment, and reporting history depend on *current* placement; moving a learner rewrites the lens on their history.
3. **No teacher/class/subject assignment model** — teacher authorization is permission-based, not assignment-aware; quiz eligibility uses current placement.
4. **Billing is demo-only** (hackathon subscription card); `core/Billing` is a stub — no payment gateway or invoicing.
5. **Placeholder adapters** — Claude/Gemini AI providers and some Storage/Mail/Notification adapters deliberately throw when selected.
6. **Module registry** enable/disable state does not dynamically unload registered providers.
7. **2FA not enforced**; stronger auth is planned.
8. **Deployment automation and tested backup restore** are planned, not done.
9. **Doc drift:** `docs/documentation-audit.md` (2026-07-16) claims "no hosted CI workflow" — stale, since `.github/workflows/ci.yml` and `deployment-validation.yml` now exist.

## Where to go next

See [plan.md](plan.md) for the prioritized path forward.
