# Roadmap

This roadmap reflects intended sequencing, not committed dates. It will evolve as Core and modules are actually built.

## v1.0 — Foundation Goes Live

- Platform Core: Authentication, RBAC, Users, Settings, Branding, Notifications, Audit Logs, Storage, File Management, Logging — **Core implemented** (Authentication, RBAC, Users, Settings, Branding, Notifications, Audit Logs, Storage, Logging, Module Manager, API foundation); File Management not yet started.
- AI Gateway (initial provider(s) wired, gateway contract enforced) — **implemented**: Ollama and DeepSeek are live providers; OpenAI, Claude, and Gemini are registered, plug-and-play placeholders per [AI Gateway](ai/ai-gateway.md).
- Public REST API (v1) covering Core capabilities — **implemented** for the Core surface above; no educational endpoints exist.
- Schools module (baseline: institution profile, staff, learners, classes) — not started; no educational modules exist yet, by design (see [Module System](architecture/module-system.md)).
- Tutoring module (baseline: individual tutor/tutoring centre profile, students, sessions) — not started.
- Billing and Licensing (baseline: tenant subscription/module entitlement) — **implemented**: see [Licensing](../core/Licensing/README.md) and [Subscriptions](../core/Subscriptions/README.md).

## Enterprise Infrastructure Layer — implemented

Built between v1.0 Core and the first educational module, per the same "no educational features" discipline: Licensing, Subscriptions, Deployment profiles, expanded Storage (S3 live; Azure/GCS placeholders), Mail provider abstraction, expanded Notifications (SMS/WhatsApp/push placeholders), expanded Audit Centre (category search), Health monitoring, API Gateway additions (rate limiting, request logging/metrics, reusable query helpers), the platform's named-queue taxonomy, a Backup framework (restore is future work), Scheduler wiring, Feature Flags, Platform Analytics (infrastructure only, no dashboards), a Security Centre (trusted devices, IP restrictions, session management, suspicious-login detection), and an interactive Installer (`platform:install`). See each service's own README under `/core` for detail — [`core/README.md`](../core/README.md) is the index.

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

## How This Roadmap Is Maintained

Each item, once actively being built, should be tracked as GitHub issues/milestones referencing this roadmap section. This document stays the high-level index; issue tracking carries the detail.
