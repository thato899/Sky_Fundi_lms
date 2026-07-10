# Roadmap

This roadmap reflects intended sequencing, not committed dates. It will evolve as Core and modules are actually built.

## v1.0 — Foundation Goes Live

- Platform Core: Authentication, RBAC, Users, Settings, Branding, Notifications, Audit Logs, Storage, File Management, Logging
- AI Gateway (initial provider(s) wired, gateway contract enforced)
- Public REST API (v1) covering Core capabilities
- Schools module (baseline: institution profile, staff, learners, classes)
- Tutoring module (baseline: individual tutor/tutoring centre profile, students, sessions)
- Billing and Licensing (baseline: tenant subscription/module entitlement)

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
