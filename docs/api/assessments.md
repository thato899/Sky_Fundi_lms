# Assessments API

Authenticated `/api/v1` routes provide organization-scoped category lifecycle, assessment CRUD/lifecycle, atomic marks, learner histories, gradebook, factual summaries, and CSV export. Trusted organization context is mandatory; request organization IDs are prohibited and foreign UUIDs return 404.

Assessment routes include `/assessments`, lifecycle actions under `/assessments/{uuid}`, `/assessment-categories`, `/gradebook`, `/learners/{uuid}/results`, and `/assessment-reports/summary`. Percentages are calculated server-side to two decimals. Private notes are excluded from ordinary resources and exports.

## Adaptive study plans

Authenticated organization-scoped endpoints:

- `GET /api/v1/study-plans/{plan}` — learner-safe published plan projection.
- `POST /api/v1/study-plans/{plan}/progress` — record completed activities and study time.
- `POST /api/v1/study-plans/{plan}/retest` — submit targeted revision responses for AI evaluation.
- `POST /api/v1/quiz-attempts/{attempt}/study-plans/regenerate` — create a new teacher draft version.
- `POST /api/v1/study-plans/{plan}/publish` — publish a teacher-approved draft version.
- `GET /api/v1/study-plans/analytics` — organization-scoped teacher analytics.

Learner responses never contain provider/model, confidence, internal reasoning, token usage, estimated cost, or guardian data.
