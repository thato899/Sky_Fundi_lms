# Sky Fundi — Project Documentation

Sky Fundi is a modular, multi-tenant education platform for schools, tutors, and training providers. It connects the full assessment loop — a teacher creates a quiz, a learner writes it, AI suggests marks that the teacher approves, and the family sees the released result with a personalized study plan — on top of an enterprise-grade Laravel foundation with organization-scoped tenancy, auditing, and role-based access.

**Stack:** Laravel 12 · PHP 8.3 · MySQL · server-rendered Blade (no JS build pipeline) · Docker. **Quality:** 274 automated tests / 1,537 assertions, Pint + PHPStan level 5, GitHub Actions CI on every push.

---

## Quick start

```bash
docker compose up --build init
docker compose up -d
docker compose exec app php artisan migrate --seed
```

The app runs at `http://localhost:8000` (Mailpit at `:8025`). To load the guided demo world, set a local `HACKATHON_DEMO_PASSWORD` (12+ characters) in `.env`, then:

```bash
docker compose exec -T app php artisan db:seed --class="Database\\Seeders\\HackathonDemoSeeder"
```

Demo logins (all share the password you set): `admin@ubuntu-future.demo`, `math.teacher@ubuntu-future.demo`, `lerato@ubuntu-future.demo` (learner), `thandi@ubuntu-future.demo` (guardian). Full runbook: [docs/development/LOCAL_RUNBOOK.md](docs/development/LOCAL_RUNBOOK.md) · demo script: [docs/hackathon-demo.md](docs/hackathon-demo.md) · video kit: [docs/demo-video-script.md](docs/demo-video-script.md).

---

## The four personas

Signing in routes every person straight to their own workspace — the navigation and landing page adapt to who you are (learner, guardian, teacher, staff, or super admin), with a persona chip in the header.

### Organization administrator
Runs the school: dashboards with KPIs and setup gaps, learner and staff administration (numbering, lifecycle, placement), guardian management and email invitations, academic structure (years, terms, grades, classes, subjects), attendance, report-card configuration (grading scales, periods, templates), scheduling (rooms, timetables, lessons with conflict detection), audit logs, and the **subscription & profitability** view — revenue vs variable cost, contribution margin, capacity meters, and upgrade scenarios.

### Teacher
Owns the assessment loop end to end: creates quizzes (objective and written questions with model answers, rubrics, and key concepts), publishes to their class, and marks submissions. Objective answers are **marked automatically**; for written answers the teacher can request an **AI-suggested mark** with rationale, strengths, improvements, and misconceptions — and always keeps the final say. On release, the learner gets a versioned **adaptive study plan**, and the teacher can write a **report to the parent**: a motivational note about the learner's performance that reaches the guardian portal and the learner's result page. An intervention dashboard surfaces at-risk learners, weak concepts, and cohort trends.

### Learner
Lands on **My quizzes**: assigned quizzes, mastery and readiness stats, and study-plan progress. Takes quizzes in a focused interface, and after release sees marks per question, teacher feedback, the teacher's message, and a 7-day study schedule with checkable activities, graded revision exercises, and a retest. **My report cards** shows published report-card snapshots — subjects with grade bands, overall average, attendance summary, and the teacher's overall comment.

### Guardian (parent)
Onboarded through secure, expiring email invitations. The portal shows only their linked learners' **released** results — score, per-question feedback, study-plan progress, and the teacher's report — and nothing else: no other learners, no private contact data, no unreleased marks. Guardians and learners are notified when a report card is published.

---

## Feature highlights

- **AI with teacher oversight** (see [docs/ai-marking.md](docs/ai-marking.md)): all AI calls go through a central gateway (OpenAI, DeepSeek, and self-hosted Ollama live; per-organization encrypted credentials supported). AI suggestions never become official without teacher approval, and one bounded regeneration is allowed. Minimal context is sent — never learner contact details or guardian data.
- **Always-working study plans**: plans are AI-generated when a provider is configured; otherwise a **deterministic performance-based fallback** builds the plan from the marked weak concepts — a released result always ships with a plan.
- **Teaching assignments** ([ADR-009](docs/adr/009-teaching-assignments.md)): date-ranged teacher-to-class/subject assignments with opt-in per-organization enforcement — marking, attendance staffing, and lesson staffing can require a covering assignment, with an administrator bypass permission.
- **Historical enrolment** ([ADR-008](docs/adr/008-historical-enrolment.md)): date-ranged placement history means moving a learner mid-year no longer rewrites their attendance, assessment, or report-card history.
- **Report cards**: immutable versioned snapshots with lifecycle (generated → reviewed → approved → published → withdrawn), grading-scale bands, PDF and formula-safe CSV export, and publication notifications.
- **Multi-tenant by construction** ([ADR-003](docs/adr/003-organization-isolation.md)): every organization-owned row carries `organization_id`; cross-organization access returns 404, verified by dedicated isolation tests.
- **Auditability**: every consequential action writes an audit record; status and enrolment histories are append-only.

---

## Architecture in sixty seconds

```
app/        Thin web host: entry, auth, dashboards, persona navigation
core/       Platform services: Auth, RBAC, Identity, AI Gateway, Notifications,
            Settings, Audit Logs, Licensing, Subscriptions, Health, ...
modules/    Bounded contexts: Academics, Learners, Staff, Assessments,
            Attendance, Reports, Scheduling, Organizations
```

Each module is clean-architecture layered (Domain / Application / Http / Infrastructure), owns its migrations and tests, declares its dependencies and permissions in a manifest, and exposes a versioned `/api/v1` REST surface that the Blade UI consumes. Cross-cutting concerns (AI, notifications, settings, audit) are only reached through `core/` gateways. Start with the [architecture overview](docs/architecture/overview.md) and the [decision records](docs/adr/README.md).

---

## Documentation index

| Area | Where |
|---|---|
| Architecture & multi-tenancy | [docs/architecture/](docs/architecture/README.md) |
| Decision records (ADR 001–009) | [docs/adr/](docs/adr/README.md) |
| API contracts per module | [docs/api/](docs/api/README.md) |
| AI gateway & marking | [docs/ai/](docs/ai/README.md), [docs/ai-marking.md](docs/ai-marking.md) |
| Local development & testing | [docs/development/](docs/development/README.md) |
| Deployment (Docker, cPanel) | [docs/deployment/](docs/deployment/README.md), [docs/cpanel-deployment.md](docs/cpanel-deployment.md) |
| Demo walkthrough & video kit | [docs/hackathon-demo.md](docs/hackathon-demo.md), [docs/demo-video-script.md](docs/demo-video-script.md) |
| Status & plan | [status.md](status.md), [plan.md](plan.md) |
| Roadmap | [docs/roadmap.md](docs/roadmap.md) |

Each module and core service also carries its own `README.md` describing scope, contracts, and explicit non-goals.
