# Staged delivery ledger

This is the canonical concise record of what is deployed, what is next, and the evidence behind each stage. It is maintained under the rules in [CODEX.md](../../CODEX.md).

The ordered path from this baseline to a production-ready release is in [the build roadmap](BUILD_ROADMAP.md).

## Release principles

- A stage is a deployable vertical slice, not a collection of unrelated changes.
- A stage documents its data migration and rollback posture before implementation.
- A stage is not marked complete until verification evidence is recorded.
- The `main` branch represents the latest integrated stage; feature branches may describe proposed work but never rewrite shipped history.

## Baseline — integrated platform (2026-07-21)

**Status:** shipped on `main` (`b92db34` at initialization of this ledger).

**Outcome:** organizations can operate a tenant-safe Laravel 12 education platform spanning academic setup, staff and learner administration, guardian access, attendance, assessments, report cards, and scheduling.

**Scope:** Platform Core, organization identity/RBAC, Academics, Staff, Learners/guardians, Attendance, Assessments, Reports, Scheduling, Blade management surfaces, Docker stack, queue, scheduler, Mailpit, health, and AI gateway foundations.

**UX contract:** persona-aware, server-rendered Blade journeys for administrators, teachers, learners, and guardians; organization-scoped navigation; accessible shared layout; no JavaScript build pipeline.

**Technical/deployment contract:** Laravel 12/PHP 8.3, MySQL, Docker Compose `init` service followed by app/queue/scheduler services. See [the runbook](../development/LOCAL_RUNBOOK.md) and [boot verification report](../development/BOOT_VERIFICATION_REPORT.md).

**Evidence available at baseline:** `composer.lock`, Compose/bootstrap verification, test and quality records, and GitHub CI configuration. Refer to the boot report for the original executed-command record; rerun checks for a new environment before treating it as production evidence.

**Known boundaries:** production deployment automation, a tested backup restore, enforced 2FA, real billing, mobile/offline clients, and some gateway adapters remain outside this stage.

## Phase 1 — learner invitation and onboarding

**Status:** shipped on `main` through PR #50 (`9100dcf`, 2026-08-09). GitHub Actions completed the full test suite and Pint checks successfully.

**Outcome:** an authorized organization administrator can invite a learner to activate their own portal account through a secure, understandable, accessible onboarding journey.

**Scope:** secure, expiring, revocable learner invitations; acceptance for new and existing users; learner-to-user/membership linking; learner-facing onboarding and first-success state; transactional audit and notification integration; focused API/web tests and documentation.

**Non-goals:** guardian workflow changes, automatic invitations, passwordless authentication, SSO, mobile apps, onboarding unrelated to learner portal access, broad UI redesign, or new assessment/attendance features.

**UX contract:**

- Administrators see clear invitation state, resend/revoke affordances, confirmation feedback, and no exposed raw token.
- Learners receive a concise invitation page with expiry/support guidance, accessible validation, and an explicit completion state.
- Existing-account acceptance cannot silently authenticate a different email address.
- Empty, expired, revoked, invalid, and already-accepted states are distinct, safe, and comprehensible.

**Technical contract to validate before coding:**

- Learners owns learner-profile invitation policy, service, views/routes, and tests; Identity owns memberships; Core Notifications owns dispatch.
- Store only a token hash; use single-use, bounded-expiry tokens and rotate safely on resend.
- All writes must be organization-scoped, transactional, idempotent where retries occur, and audited.
- Additive migration only, with a complete rollback and no committed credentials.
- Reuse Guardian invitation conventions only after confirming learner-specific portal visibility and authorization semantics.

**Deployment gate:** a migration-forward/rollback check, locked dependency installation, targeted invitation tests, full test suite, Pint, PHPStan for changed production code, health check, and a documented feature-flag/rollout decision if backward compatibility requires one.

**Verification evidence:** GitHub Actions runs `31321345100` (tests) and `31321347581` (Pint) completed successfully before merge. The stage has no database migration; it reuses the existing organization-scoped membership invitation storage and rollback posture.

**Follow-ups after completion:** invitation administration bulk tooling only if evidence supports it; learner portal attendance/timetable slices; deployment automation and restore validation remain separate stages.

## Phase 2 — teaching assignment administration

**Status:** shipped on `main` through PR #51 (`8352f47`, 2026-08-09). GitHub Actions completed the full test suite and Pint checks successfully.

**Outcome:** authorized organization administrators can see, add, and end a staff member's class/subject coverage through a clear web journey or tenant-safe API. This exposes the already-integrated enforcement layer used by attendance, assessment, and scheduling work.

**Scope:** teaching-assignment web management surface, scoped API endpoints/resource, explicit permission gates, helpful assignment states, and organization-safe record resolution. No migration is required because the `staff_teaching_assignments` schema and service shipped in the baseline.

**Deployment gate:** full test suite and Pint in GitHub Actions; verify API and web route registration, tenant boundaries, and unchanged existing enforcement tests. Rollback is application-only: remove the routes/controllers/views without touching assignment data.

**Verification evidence:** GitHub Actions runs `31321727035` (tests) and `31321738077` (Pint) completed successfully before merge. No migration was introduced.

## Phase 3 — academic-cycle readiness

**Status:** in progress.

**Outcome:** the organization dashboard turns existing attendance, assessment, reporting, and scheduling records into a concise current-year operational view, so administrators can identify unfinished cycle work without mutating any academic data.

**Scope:** current-year read-only counts for open attendance, finalized-but-withheld assessments, open reporting periods, unpublished report cards, and the next seven days of scheduled lessons; direct links to the responsible workflow; a clear empty state when no current academic year exists.

**Deployment gate:** full test suite and Pint in GitHub Actions. This is an application-only, read-only change with no migration; rollback is removal of the dashboard aggregation and view section.

## Updating this ledger

At the end of each phase, add or update its entry with the final commit/PR, shipped outcome, exact files/contracts changed, migration/rollback evidence, exact test/quality totals, deployment command results, known warnings, and the next narrow phase. Keep this document factual and link detailed artifacts rather than duplicating them.
