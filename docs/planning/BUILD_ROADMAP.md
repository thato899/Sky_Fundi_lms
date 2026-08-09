# Build roadmap — from integrated platform to successful release

This roadmap converts the implemented Sky Fundi baseline into a sequence of safe, usable, and deployable releases. It intentionally does not assign dates: a phase moves only when its outcome and evidence are complete. The detailed record for each phase lives in [the delivery ledger](DELIVERY_LEDGER.md).

## What “successfully built” means

The first production-ready release must let a school administrator safely set up an organization, manage academic structures, staff and learners, invite guardians and learners, run an assessment-to-reporting cycle, and support the resulting workflows with reliable operations.

Success is measured across five dimensions:

| Dimension | Release standard |
| --- | --- |
| User experience | Each supported persona can complete its core journey on desktop and mobile-sized screens, with clear states, accessible forms, actionable errors, and no dead-end pages. |
| Trust | Organization isolation, RBAC, audit records, secure invitations, safe token handling, and privacy boundaries are exercised by automated and end-to-end tests. |
| Operability | A documented deployment can initialize, migrate, roll back safely, expose health, deliver mail, process jobs, schedule work, back up, and restore in a tested environment. |
| Quality | Locked dependencies, green CI, migrations/rollback checks, focused tests plus full suite, Pint, and Laravel-aware static analysis are part of each release gate. |
| Commercial readiness | Licensing and subscription foundations are connected to a clear product policy and supportable operational process before real billing is enabled. |

## Delivery path

### Phase 0 — release foundation and evidence

**Status:** integrated baseline; maintain continuously.

**Outcome:** every engineer can start a known-good local stack and every release has trustworthy evidence.

**Work:** keep Docker/init/health workflows current; preserve `composer.lock`; align documentation with executable behavior; repair CI/environment drift; record actual command results; establish preview/staging configuration and secrets handling without committing secrets.

**UX focus:** system feedback is part of UX—friendly maintenance states, safe error pages, clear empty states, and no silent failure when mail, AI, or optional services are unavailable.

**Exit evidence:** `make init`, `make up`, migrations, tests, Pint, static analysis, `/up`, queue, scheduler, Mailpit, and backup/restore checks are run in the target environment and documented.

### Phase 1 — learner invitation and first-login experience

**Status:** next planned vertical slice.

**Outcome:** an administrator invites a learner; the learner securely accepts, creates or links an account, reaches a useful first screen, and can understand what to do next.

**Scope:** invitation lifecycle, portal access linkage, learner-specific policy/service/routes/views, email, audit, expiry/revocation/resend, existing-user safety, and tests. Reuse the proven guardian-invitation patterns only where learner ownership permits.

**UX focus:** invitation status in the learner directory, calm and concise acceptance pages, accessible password/input feedback, mobile-first completion, and purposeful first-login guidance.

**Exit evidence:** forward/rollback migration check; invitation state-machine, authorization/isolation, email, and acceptance tests; an administrator-to-learner browser journey; full quality gate.

### Phase 2 — staff assignment administration and daily teaching readiness

**Outcome:** organization administrators can manage teacher/class/subject assignments without database intervention, and teachers see only the actions and classes they are authorized to manage.

**Scope:** assignment administration web/API surface and practical bulk tooling; clear assignment coverage and expiry; teacher-focused dashboard/navigation refinements; attendance, assessment, and scheduling integration checks.

**UX focus:** a visual coverage model, useful empty states for unassigned teachers, safe bulk-preview/confirmation flows, and explicit authorization messages rather than generic 403 experiences.

**Exit evidence:** assignment lifecycle/isolation tests; cross-module authorization regression suite; representative admin and teacher journeys; mobile/responsive review of management screens.

### Phase 3 — complete the academic cycle with confidence

**Outcome:** schools can run daily attendance, assessments, marking, release, reporting, and timetable workflows with understandable status and trustworthy historical data.

**Scope:** harden the thinly tested Attendance, Reports, Scheduling, and Staff areas; improve historical enrolment surfaces/corrections; improve operational exports and data-validation feedback. No new unrelated modules.

**UX focus:** status-led workflows (draft/open/finalized; generated/reviewed/approved/published), clear publish consequences, accessible data tables and filters, resilient empty states, and printable/export-safe reports.

**Exit evidence:** targeted module test expansion, import/export safety checks, data-isolation and historical-placement regression tests, persona end-to-end journeys, and accessibility review of the principal workflows.

### Phase 4 — family engagement and learning continuity

**Outcome:** learners and guardians reliably receive only the right released information, can act on study guidance, and understand upcoming learning obligations.

**Scope:** learner portal attendance/timetable views; notification preference/visibility hardening; content/homework only after a separately approved product slice; no broad mobile-client build in this phase.

**UX focus:** low-cognitive-load learner and guardian pages, clear release timing, privacy-first summaries, usable on a phone browser, and graceful offline/temporary-error messaging.

**Exit evidence:** visibility/privacy matrix tests; notification delivery/failure tests; learner and guardian walkthroughs using production-like data; accessibility and responsive checks.

### Phase 5 — operational trust, security, and deployability

**Outcome:** a school can deploy, recover, monitor, and support the platform with documented confidence.

**Scope:** production deployment automation; tested backup restore; health/observability coverage; enforceable two-factor authentication strategy; rate-limit and security review; provider outage drills; data-retention and support runbooks.

**UX focus:** administrators see actionable health/support information without sensitive details; authentication recovery is understandable and secure; operational errors guide an operator toward resolution.

**Exit evidence:** reproducible staging deployment; restore drill; migration compatibility test; incident/rollback rehearsal; security review; alert/health validation; release checklist signed with exact command output.

### Phase 6 — commercial release readiness

**Outcome:** the platform is ready for controlled customer onboarding and paid operation.

**Scope:** decide pricing and billing gateway before implementing real charges; licensing/subscription lifecycle; tenant onboarding playbook; support/SLA policy; legal/privacy review; pilot feedback loop; performance/load targets based on real schools.

**UX focus:** clear plan/status language, no surprise lockouts, transparent limits, and an administrator-friendly upgrade/support journey.

**Exit evidence:** pilot onboarding completed, restore/incident process exercised, billing decisions documented, privacy/security review completed, production monitoring established, and pilot acceptance criteria met.

## Enterprise roadmap — surpassing assessment-first platforms

Sky Fundi will be the operating and learning-intelligence system for school groups: a trusted learner record from admission through intervention, family communication, compliance, and executive decision-making.

| Horizon | Product promise | Build priorities |
| --- | --- | --- |
| Trusted school core | Accurate, permissioned, auditable records at scale. | Finish Phase 4; bulk import/export with previews; academic-year rollover; historical corrections; approval workflows; multi-campus-ready model. |
| Learning intelligence | Explainable early-warning and intervention support. | Curriculum/standards map; mastery; cohort risk signals; intervention plans; moderated assessment; auditable AI copilot. |
| Connected family experience | Relevant, privacy-safe information on a phone. | Guardian summaries; notification preferences; WhatsApp/email/SMS adapters; emergency messaging; appointments; multilingual portal. |
| Enterprise operations | Group-wide control without losing campus autonomy. | Campus hierarchy; group dashboards; admissions; documents; HR/payroll integrations; APIs/webhooks; later finance/transport/library modules. |
| Operational trust | Deploy, recover, secure, and support with confidence. | Staging/production automation; restore drills; observability; SSO/2FA; POPIA retention/export/deletion controls; incident runbooks. |

### Differentiators to protect

1. One organization-scoped learner record across attendance, timetable, assessment, reporting, interventions, and family communication.
2. Explainable AI: source data, limitations, human approval, and audit history for every recommendation.
3. Group → campus → grade → class visibility, delegated administration, bulk operations, and audit exports.
4. Guardian privacy enforced through relationship, effective dates, communication preference, and publication state—not merely a login.
5. South African readiness: POPIA controls, CAPS-first curriculum packs, multilingual communication, and SA-SAMS/LURITS integration discovery.

### Next three deployable slices

1. Complete guardian summaries and privacy-matrix automation.
2. Academic rollover and bulk learner/staff import with dry-run, approval, errors, and rollback.
3. Phase 5 staging, backup/restore evidence, observability, and SSO/2FA design.

## Environment promotion model

| Environment | Purpose | Promotion gate |
| --- | --- | --- |
| Local | Fast development and focused tests using Docker Compose. | Targeted tests and a clean diff. |
| Preview | Per-branch review of the full vertical slice. | CI green, migration compatibility reviewed, UX walkthrough complete. |
| Staging | Production-like integration, seeded safely without real personal data. | Full release gate, restore drill where data changes, persona smoke tests. |
| Production | Controlled customer/pilot release. | Approved release notes, rollback plan, monitoring/backup verification, and owner sign-off. |

## Non-negotiable release gates

Every product phase must demonstrate, where relevant:

1. organization-scoped authorization and audit behavior;
2. forward migration and rollback safety;
3. targeted automated coverage plus the full suite;
4. formatting and changed-path static analysis;
5. responsive, keyboard-accessible UI and clear empty/error/success states;
6. health, queue, scheduler, and mail behavior where the workflow depends on them;
7. updated module/readme/runbook/ledger documentation with only actual evidence;
8. a focused PR with a human-readable rollout and rollback note.

## Deferred intentionally

Mobile-native/offline clients, library, transport, sports, finance modules, online examinations, broad AI expansion, and new integrations remain future initiatives. They must enter through their own discovery and deployable-stage proposal; they do not belong as incidental additions to the phases above.
