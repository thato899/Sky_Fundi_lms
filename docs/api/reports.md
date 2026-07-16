# Reports API

All endpoints require authentication, active trusted organization context, active membership, effective permission, and tenant-scoped UUID resolution.

- `GET|POST /api/v1/grading-scales`; `GET|PATCH /api/v1/grading-scales/{scale}`; POST `activate`, `deactivate`, and `default`.
- `GET|POST /api/v1/reporting-periods`; `GET|PATCH /api/v1/reporting-periods/{period}`; POST `open`, `close`, and `archive`.
- `GET|POST /api/v1/report-card-templates`; `PATCH /api/v1/report-card-templates/{template}`; POST `default`.
- `GET /api/v1/report-cards`; `POST /api/v1/report-cards/generate`; `GET /api/v1/report-cards/{reportCard}`; POST `regenerate`, `comments`, `review`, `approve`, `publish`, and `withdraw`.
- `GET /api/v1/report-cards/{reportCard}/pdf`; `GET /api/v1/report-cards/export`; `GET /api/v1/learners/{learner}/report-cards`.

Payload `organization_id` is prohibited and foreign UUIDs return 404. Directory filters include year, term, period, grade, class, learner, and status. Responses hide raw snapshot metadata and assessment private notes. CSV excludes comments, guardian data, and metadata and neutralizes spreadsheet formulas.

See the Reports module README for eligibility, weighting, attendance, snapshot, and lifecycle rules.
