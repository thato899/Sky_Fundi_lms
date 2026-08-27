# modules/Leaderboards

**Purpose**: principal-curated academic and sports leaderboards, plus a Sportsperson of the Week spotlight. Ranks learners without ever exposing the marks behind the ranking.

## The visibility contract

This is the part of the feature that matters most, so it is enforced at several layers rather than trusted to any single one:

- **A learner (or their guardian) can always see their own rank and value** — never gated behind publication.
- **No one else's rank or value is visible until a `leaderboards.manage` holder (the principal, by default — see Permissions below) explicitly publishes the leaderboard.** Published leaderboards are visible to everyone in their scope; private ones are visible in full only to `leaderboards.manage`/`leaderboards.view_organization` holders.
- **An entry never carries anything beyond a rank and a single aggregate value.** `LeaderboardEntry` has no column for a subject-by-subject mark, and `Http/Resources/LeaderboardEntryResource` only ever serializes `rank`, `value`, and (only when the full list is visible) the learner's display name. There is nowhere for a mark to leak through even by accident.

## Two ranking sources, one reason for the split

- **Academic** leaderboards read already-approved `Modules\Reports\Infrastructure\Models\ReportCard` / `ReportCardSubjectResult` snapshots for a chosen `ReportingPeriod` — the same overall average or per-subject percentage a family already sees on a published report card — rather than re-deriving an average independently. A leaderboard can never disagree with the report card it was built from, and only report cards with `approved`/`published` status count (never a draft in progress).
- **Sports** leaderboards sum `SportsRecord` points (see `Application/SportsRecordService`) logged by staff over a chosen date range. There is no official record to defer to here — this platform has no sports module of its own — so `SportsRecord` is a deliberately minimal points log, not a fixtures/results system.

Both support an optional grade or class scope; omitting both ranks the whole school.

## Sportsperson of the Week

A separate, simple spotlight: the principal posts a learner + citation for a given week (`SportspersonOfTheWeek`), which stays a private draft until published. Publishing notifies the learner and their guardians through `Core\Notifications`, the same pattern `Modules\Reports\Application\ReportCardService` uses for report-card publication.

## Permissions

- `leaderboards.manage` — generate, publish, and unpublish leaderboards, and post/publish Sportsperson of the Week. Granted only to Organization Administrator (and Super Admin) by default — leaderboard arrangement is a principal-level decision per the feature's own design, not extended to Academic Administrator the way most org-wide capabilities in this codebase are.
- `leaderboards.view_organization` — see the full ranked list of any leaderboard even before it is published, without being able to generate or publish one. Granted to Academic Administrator and Teacher by default.
- `sports_records.manage` — log or remove sports results. Granted to Organization Administrator, Academic Administrator, Teacher, and Tutor.

## Allowed dependencies

`Modules\Reports` (ReportCard/ReportCardSubjectResult, read-only), `Modules\Learners` (LearnerProfile/GuardianProfile), `Modules\Academics` (Grade/ClassGroup/Subject scope filters), `Core\AuditLogs`, `Core\Notifications`. Declared in `module.json`.

## Known gaps, disclosed rather than hidden

- No CSV/print export of a leaderboard.
- No automatic recurring generation (e.g. "regenerate every Friday") — a `leaderboards.manage` holder generates each one explicitly.
- No organization-admin self-service UI to change who holds `leaderboards.manage`/`leaderboards.view_organization`/`sports_records.manage` beyond the seeded defaults above — same gap already noted for `enforce_two_factor` and `enforce_teaching_assignments`.
