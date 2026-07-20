# Demo user profiles — test logins

> **Demo accounts only.** These users exist only after running `HackathonDemoSeeder` on a local/demo database. The shared password is whatever you set as `HACKATHON_DEMO_PASSWORD` in your local `.env` (12+ characters; the value below is the local demo default used in this project's walkthroughs). Never reuse these credentials in production — production has no seeded users and no committed secrets.

**App:** `http://localhost:8000` (or `http://localhost:8001` if you mapped an alternate port in a local `compose.override.yaml`) · **Mailpit inbox:** `http://localhost:8025`

**Shared demo password:** `SkyFundi-Demo-2026!`

| Persona | Email | What you land on | What to test |
|---|---|---|---|
| **Organization administrator** | `admin@ubuntu-future.demo` | `/dashboard` | School KPIs, learners, staff, guardians, academics, attendance, reports config, scheduling, audit; **Subscription & profitability** at `/subscription`. Holds `teaching_assignments.bypass`, so can mark any class. |
| **Teacher — Naledi Dlamini** | `math.teacher@ubuntu-future.demo` | `/assessments` | Create/publish quizzes for Grade 10 · 10A Mathematics (she holds the seeded teaching assignment), review submissions, request AI marks, approve, release, write the **report to parent**, intervention dashboard at `/quizzes/interventions`. |
| **Tutor — Kabelo** | `tutor@ubuntu-future.demo` | `/assessments` | The *negative* test: has marking permissions but **no teaching assignment** — opening a 10A submission review returns 403 (enforcement demo). |
| **Learner — Lerato Molefe** | `lerato@ubuntu-future.demo` | `/quizzes/assigned` | Take assigned quizzes, see the released result with per-question feedback and the teacher's message, work the 7-day study plan (check activities, retest), **My report cards** at `/my/report-cards`. |
| **Guardian — Thandi Molefe** (Lerato's mother) | `thandi@ubuntu-future.demo` | her guardian portal (`/guardians/{uuid}`) | Lerato's released score, per-question feedback, study progress, and the teacher's report. Denied on admin surfaces (`/dashboard`, `/learners`, `/subscription` → 403). |
| **Unrelated guardian** | `unrelated.guardian@ubuntu-future.demo` | guardian portal | The isolation test: sees **no** learner data — not linked to any learner. |

## Non-login demo records

- **Amo Khumalo** (`UFA-2026-099`) — learner profile in 10A with portal access **disabled**; proves profile-only learners exist without accounts.
- **Demo School** (`UFA-DEMO`) — the organization; teaching-assignment enforcement is seeded **on** (`staff.enforce_teaching_assignments`).
- Seeded quiz: **"Forces and Linear Equations Check-in"** with Lerato's released 15/20 attempt, AI feedback, and a published study plan — so the full story demos even before you create anything.

## Reseeding

Non-destructive (idempotent):

```bash
docker compose exec -T app php artisan db:seed --class="Database\\Seeders\\HackathonDemoSeeder"
```

Destructive full reset (local only): `make demo-reset`.
