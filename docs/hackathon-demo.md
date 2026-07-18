# Sky Fundi hackathon demonstration

## Product story and architecture

Sky Fundi connects school onboarding, teacher-reviewed assessment feedback and a credible commercial model. Target customers are schools, tutors and training providers; personas are administrator, teacher, learner and guardian.

The Laravel platform uses organization ownership as its tenant boundary. Quiz capability extends Assessments and reuses Academics, Learners, Staff, RBAC, auditing, notifications, licensing and subscriptions. AI calls pass only through Core AIGateway.

Question totals recalculate the assessment maximum. Current-class result rows are assignments. Attempts are idempotent and submitted transactionally; submitted answers cannot change. Objective answers are server-marked. Written answers retain separate AI suggestions and teacher-approved marks. Only released attempts and approved plans appear to learners or guardians.

Teacher assignment and historical enrolment records do not yet exist, so the MVP enforces creator ownership plus permissions and current class placement.

## AI, oversight, study plan and privacy

1. Mark objective answers without AI.
2. Send minimized rubric context for submitted written answers.
3. Validate strict structured output and bound the mark.
4. Let the teacher accept/override marks and edit feedback.
5. Generate a practical seven-day plan from actual weaknesses.
6. Require teacher plan approval before release.

No address, guardian data, invitation token, API key, raw prompt/response or hidden reasoning is rendered. Guardian visibility uses `GuardianPortalAccessService`, released results and approved high-level plans only.

## Startup, Mailpit and reset

```bash
make init
make up
docker compose exec -T app php artisan migrate
```

The app is at `http://localhost:8000`; Mailpit is at `http://localhost:8025`. Set a unique local `HACKATHON_DEMO_PASSWORD` of at least 12 characters. No demo password is stored in source.

`make demo-reset` is destructive and local/demo only. It rebuilds the database and runs `HackathonDemoSeeder`; never use it in production. Non-destructive reseed:

```bash
docker compose exec -T app php artisan db:seed --class="Database\\Seeders\\HackathonDemoSeeder"
```

Configure OpenAI per [AI marking](ai-marking.md). If unavailable, objective marks remain, written answers await manual marking, and the seeded representative result can keep the demo reliable.

## Five-to-seven-minute script

1. Show Ubuntu Future Academy’s administrator dashboard, learners, staff, guardians and subscription card.
2. Show Growth limits, estimated AI cost, contribution margin, assumptions and demo labels.
3. Sign in as the Mathematics teacher; open the seeded Grade 10 quiz and show types, model answers and rubric.
4. Open Lerato’s submission; show deterministic marks and the AI rationale/misconception.
5. Approve or adjust the mark, approve the seven-day plan and release.
6. Sign in as Lerato; show question feedback and the practical plan.
7. Sign in as Thandi; show only Lerato’s released summary and approved priorities. Explain unrelated access denial and excluded private data.
8. Return to profitability and close with payments, teacher assignment, enrolment history, richer analytics, add-ons and optional local AI.

## Known limitations and roadmap

- Demo billing only; no gateway, invoices or audited reporting.
- No authoritative teacher-class/subject assignment or historical enrolment.
- Learner accounts must already link to learner profiles.
- Study plans use a reliable bounded performance-based generator.
- No question bank, autosave, file responses, proctoring, bulk assignment, moderation or mobile/offline client.
