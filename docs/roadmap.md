# Roadmap

This roadmap reflects intended sequencing, not committed dates. It will evolve as Core and modules are actually built.

## v1.0 — Foundation Goes Live

- Platform Core: Authentication, RBAC, Users, Settings, Branding, Notifications, Audit Logs, Storage, File Management, Logging — **Core implemented** (Authentication, RBAC, Users, Settings, Branding, Notifications, Audit Logs, Storage, Logging, Module Manager, API foundation); File Management not yet started.
- AI Gateway (initial provider(s) wired, gateway contract enforced) — **implemented**: Ollama and DeepSeek are live providers; OpenAI, Claude, and Gemini are registered, plug-and-play placeholders per [AI Gateway](ai/ai-gateway.md).
- Public REST API (v1) covering Core capabilities — **implemented** for the Core surface above; no educational endpoints exist.
- Schools module (baseline: institution profile, staff, learners, classes) — not started. The reusable academic engine it will build on ([`Academics`](../modules/Academics/README.md)) is now implemented — see below.
- Tutoring module (baseline: individual tutor/tutoring centre profile, students, sessions) — not started.
- Billing and Licensing (baseline: tenant subscription/module entitlement) — **implemented**: see [Licensing](../core/Licensing/README.md) and [Subscriptions](../core/Subscriptions/README.md).

## Enterprise Infrastructure Layer — implemented

Built between v1.0 Core and the first educational module, per the same "no educational features" discipline: Licensing, Subscriptions, Deployment profiles, expanded Storage (S3 live; Azure/GCS placeholders), Mail provider abstraction, expanded Notifications (SMS/WhatsApp/push placeholders), expanded Audit Centre (category search), Health monitoring, API Gateway additions (rate limiting, request logging/metrics, reusable query helpers), the platform's named-queue taxonomy, a Backup framework (restore is future work), Scheduler wiring, Feature Flags, Platform Analytics (infrastructure only, no dashboards), a Security Centre (trusted devices, IP restrictions, session management, suspicious-login detection), and an interactive Installer (`platform:install`). See each service's own README under `/core` for detail — [`core/README.md`](../core/README.md) is the index.

## Education Domain Foundation — implemented

[`modules/Academics`](../modules/Academics/README.md) — the platform's first real module, and the first genuinely educational content in this repository: academic years, terms, grades, classes, subjects, departments, curricula (database-driven, not hardcoded), academic calendar, and a reusable timetable foundation (no scheduling/generation logic yet). Deliberately independent of any organisation type — Schools, Tutoring Centres, Training Academies, and Colleges are all expected to build on this same engine rather than each defining their own. Known gap: no organisation/tenancy scoping exists yet on its tables — see the module's own README for detail.

## v1.5 — Day-to-Day Academic Operations

- Attendance module
- Homework module
- Assessments module
- Reports module (built against Attendance/Homework/Assessments data via events/service interfaces, per [Module System](architecture/module-system.md))

## v2.0 — Institution Breadth and Mobile

- Library module
- Sports module
- Transport module
- Finance module (broader than Billing — fees, invoicing per institution)
- Messaging module
- Mobile (Flutter/Android clients against the existing v1 API — see [Mobile](mobile/README.md))
- Offline AI (Ollama-first offline capability via the AI Gateway — see [AI Gateway](ai/ai-gateway.md))

## Later / Unscheduled

- Newsletters, Hostel, Clinic, Visitors, Inventory modules
- Additional tenant types beyond the initial set (see [Multi-Tenancy](architecture/multi-tenancy.md))
- Additional AI providers as the Gateway's adapter set grows

## Organization Management — implemented

`modules/Organizations` is the organization tenancy foundation: lifecycle, organization-scoped settings, inherited branding, administrator membership, licensing metadata, encrypted AI configuration, and module assignment. New tenant-owned modules should reference and scope queries by `organization_id`.

## How This Roadmap Is Maintained

Each item, once actively being built, should be tracked as GitHub issues/milestones referencing this roadmap section. This document stays the high-level index; issue tracking carries the detail.
