# Demo video kit — shot list and voiceover script

A ~6½-minute screen-recorded walkthrough of Sky Fundi. Record your screen while following the shot list; read the voiceover lines as you go. Total runtime lands between 6:00 and 7:00 at a relaxed speaking pace.

## Recording setup

- **Recorder:** Windows Game Bar (`Win + Alt + R` to start/stop) or OBS Studio at 1920×1080, 30 fps. Record the browser window only.
- **Browser:** a clean window (hide bookmarks bar, close other tabs), zoom at 110–125% so text reads well in the final video.
- **Prep checklist (do a full dry run first):**
  1. Stack running and demo seeded (`HackathonDemoSeeder`), app open on the login page.
  2. Four browser profiles or one incognito window per persona so logins are quick: admin, teacher, learner (Lerato), guardian (Thandi).
  3. A quiz with a *released* attempt exists (the seeder provides "Forces and Linear Equations Check-in" with Lerato's released 15/20 result, AI feedback, and a published study plan).
  4. Optional: an OpenAI key in `.env` if you want to show live AI suggestion; without it, the seeded AI feedback carries the story and the platform falls back gracefully.
- Pause the recording between scenes while you switch logins — the cuts read naturally.

## Personas

| Login | Password | Role |
|---|---|---|
| `admin@ubuntu-future.demo` | your `HACKATHON_DEMO_PASSWORD` | Organization administrator |
| `math.teacher@ubuntu-future.demo` | 〃 | Teacher (Naledi) |
| `lerato@ubuntu-future.demo` | 〃 | Learner (Lerato) |
| `thandi@ubuntu-future.demo` | 〃 | Guardian (Thandi, Lerato's mother) |

---

## Scene 1 · The problem and the platform — 0:00–0:35

**Show:** the landing page, then the login screen.

> "Teachers spend hours marking, learners wait days for feedback, and parents are the last to know. Sky Fundi closes that loop. It's a multi-tenant education platform where a teacher sets a quiz, AI helps mark it under the teacher's control, and the learner and their family see results and a personal study plan the moment they're released. Let me show you one complete journey."

## Scene 2 · The school at a glance (admin) — 0:35–1:20

**Show:** log in as **admin** → dashboard KPIs and setup panel → click **Learners** briefly → click **Subscription**.

> "This is Demo School's administrator view: learners, staff, guardians, and academic setup in one place, with every action audited and every record locked to this organization. And because a school is also a business, here's the subscription view — live contribution margin, revenue against variable cost including AI usage, and what growth looks like. This platform knows what it costs to run."

## Scene 3 · The teacher sets a quiz — 1:20–2:20

**Show:** log in as **teacher** — notice you land on Assessments automatically → open **Create quiz** → fill in title and class → open the quiz workspace → add one multiple-choice and one written question (paste the model answer and rubric) → **Publish**.

> "Naledi teaches Grade 10 Mathematics. Sky Fundi routes her straight to her workspace — and because teaching assignments are enforced, only she can mark this class; an unassigned tutor gets turned away. She builds a quiz in a minute: an objective question, and a written one with a model answer and marking rubric — that rubric is what the AI will mark against later. One click, and it's live for class 10A."

## Scene 4 · The learner writes it — 2:20–3:00

**Show:** log in as **Lerato** — she lands on **My quizzes** with her mastery stats → **Start** the quiz → select an answer, type a written response → **Submit final answers**.

> "Lerato signs in and lands on her own page — her quizzes, her mastery, her streak. She writes the quiz in a focused view: pick, type, submit. Her answers lock on submission, and the objective question is already marked by the server before a teacher ever looks at it."

## Scene 5 · AI-assisted marking, teacher in control — 3:00–4:15

**Show:** as **teacher**, open the submission review (from the quiz workspace submissions table) → point at the auto-marked objective answer → the AI callout on the written answer ("AI suggests… confidence, strengths, misconceptions") → adjust the mark → **Approve final marks** → **Release result** → scroll to the study plan and type a sentence into **Report to parent** → save.

> "Marking time. The objective answer is already done. For the written answer, the AI has read Lerato's response against the rubric and suggests a mark — with its rationale, her strengths, and the exact misconception it spotted. But look who decides: the teacher. Naledi adjusts the mark, approves, and releases. The platform instantly builds Lerato a personalized seven-day study plan from her actual weak concepts — and works even fully offline, with a deterministic fallback when no AI is configured. And here's my favourite part: Naledi writes a short report to Lerato's mother — a human note to motivate her, attached right to the result."

## Scene 6 · The learner's result and study plan — 4:15–5:00

**Show:** as **Lerato**, open the quiz again — now the released result: score ring, per-question feedback, "Message from your teacher", the 7-day schedule with checkboxes, revision exercises.

> "The moment it's released, Lerato sees everything: her score, feedback on every question, her teacher's message — and a seven-day plan she can actually follow: daily half-hour activities, revision exercises from easy to challenge, even an adaptive retest. Feedback stopped being a grade and became a path."

## Scene 7 · The parent sees it too — 5:00–5:45

**Show:** log in as **Thandi** — she lands directly on her guardian portal → Lerato's released score, per-question feedback, study progress bar, the teacher's note. Mention **My report cards** and the publication notification.

> "And Thandi — Lerato's mother — doesn't need to ask. Her portal shows exactly what was released and nothing more: the score, the feedback, study progress, and Naledi's note to her. When term report cards are published, learners and guardians are notified, and Lerato can open her published report card herself. Privacy is structural: guardians only ever see their own linked learners' released results."

## Scene 8 · Close — 5:45–6:30

**Show:** back on the admin **Subscription** page, then the architecture diagram section of `DOCUMENTATION.md` on GitHub (optional).

> "One platform, four people, one closed loop — assessment to insight to family — with the teacher in control of the AI at every step. Under the hood: a modular Laravel platform, strict per-school tenancy, full audit trails, 274 automated tests, and a proven margin model per school. That's Sky Fundi. Thank you."

---

## Editing notes

- Keep each login switch as a hard cut; no need to show the login form after Scene 2 — a 1-second cut to the landed page reads better.
- If a live AI call fails on camera, don't cut: the error banner plus "the teacher can always mark manually" is a *credibility moment* — the seeded attempt already shows full AI feedback in Scene 5's alternative (open Lerato's seeded "Forces and Linear Equations" review instead).
- B-roll worth capturing: the persona chip changing between logins; the intervention dashboard (`/quizzes/interventions`) if you have 20 spare seconds after Scene 5.
