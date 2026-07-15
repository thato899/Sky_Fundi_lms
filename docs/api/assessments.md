# Assessments API

Authenticated `/api/v1` routes provide organization-scoped category lifecycle, assessment CRUD/lifecycle, atomic marks, learner histories, gradebook, factual summaries, and CSV export. Trusted organization context is mandatory; request organization IDs are prohibited and foreign UUIDs return 404.

Assessment routes include `/assessments`, lifecycle actions under `/assessments/{uuid}`, `/assessment-categories`, `/gradebook`, `/learners/{uuid}/results`, and `/assessment-reports/summary`. Percentages are calculated server-side to two decimals. Private notes are excluded from ordinary resources and exports.
