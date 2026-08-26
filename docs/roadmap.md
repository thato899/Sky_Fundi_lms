# Implementation roadmap

This is an implementation-status document, not a promise of dates. Executable code, registered providers/routes, migrations, and tests determine status.

## Completed

- Laravel 12/PHP 8.3 modular platform foundation, versioned REST API, Blade entry/login, Super Admin surfaces, and organization administrator dashboard.
- Core Users, Auth/Sanctum, RBAC, Identity/memberships, Audit Logs, Settings, Branding, Notifications, Storage, Mail, AI Gateway, module registry, licensing, subscriptions, deployment profiles, health, feature flags, analytics recording, Security Centre, backup, scheduler, installer, API conventions, and logging foundations.
- Shared-database organization tenancy with UUID ownership, active membership context, scoped relationship validation, and cross-organization tests.
- Academics: curricula, departments, years, terms, grades, classes, subjects, calendar, and timetable periods.
- Staff and Learner administration, including placement, learner numbering, lifecycle history, and web/API management.
- Guardian invitation and portal onboarding, including hashed expiring tokens, queued email, new/existing-account acceptance, membership activation, and restricted linked-learner access.
- Learner invitation and portal onboarding, mirroring the guardian flow field-for-field (hashed expiring tokens, queued email, new/existing-account acceptance, membership activation, portal access enablement); the invite/resend/revoke lifecycle is also reachable from the learner profile's web UI.
- Learner Attendance, Assessments/mark sheets/gradebooks, Reports/report-card snapshots/PDF/CSV, and Scheduling/rooms/templates/lessons/conflict handling.
- Historical learner enrolment: date-ranged placement history maintained on every placement write, backfilled from current placement, with enrolment-aware report-card calculation.
- Real billing: PayFast payment gateway integration (ADR-010), subscription checkout with webhook-verified payment confirmation, a real plan catalog, usage-linked invoicing generated from Attendance/enrolment data, dunning via the existing subscription grace-period mechanism, and a guardian-facing invoice/payment-status view in the Guardian Portal.
- Real Gemini AI provider, plus retrieval-grounded assessment (ADR-011): a new Materials module for teacher-uploaded course material (text ingestion, async chunking, embeddings), a brute-force retrieval pipeline, an organization-isolated learner AI tutor chat grounded in that material, and AI-drafted quiz question generation flowing through Assessments' existing teacher review/publish gate.
- Teaching assignments: date-ranged teacher-to-class/subject assignments with opt-in per-organization enforcement across Assessments, Attendance, and Scheduling, plus assignment-aware teacher marking/release gating.
- Family visibility: report-card publication notifications to learners and guardians, the learner's published report-card portal page, teacher-authored parent reports on study plans, and a deterministic study-plan fallback so releases always ship a plan.
- Docker development stack with app, MySQL, Mailpit, queue worker, scheduler, init job, and optional Redis profile.
- PHPUnit unit/feature/security/regression suites plus migration, Pint, PHPStan, health, and aggregate verification scripts.

## In progress

- Repository documentation and architecture alignment (this milestone).
- Hardening breadth is ongoing: some implemented Core adapters are explicit placeholders, and module registry enable/disable state does not dynamically unload registered providers.

## Planned

- Enrolment history API/web surfaces and historical placement corrections.
- Homework and learning-content workflows.
- Production deployment automation and a tested restore workflow for backups.
- Stronger authentication features such as enforced two-factor authentication.
- Teaching-assignment administration surfaces (web/API) and bulk assignment tooling.
- A second payment gateway behind `Core\Billing\Contracts\PaymentGatewayInterface` (see ADR-010); live sandbox-verified webhook round-trip once merchant credentials are available.

## Future ideas

- Library, transport, finance/fees, messaging, sports, clinic, hostel, visitors, and inventory modules.
- Mobile/offline clients, calendar/conferencing integrations, online examinations, and timetable optimization.
- A real Claude adapter (the remaining AI Gateway placeholder), a second embeddings provider, PDF material ingestion, and additional live storage/mail/notification adapters through the existing gateways.

Completed functionality is described in [architecture](architecture/overview.md), [module READMEs](../modules/), and [API documentation](api/README.md). Explicit non-goals remain in each owning README.
