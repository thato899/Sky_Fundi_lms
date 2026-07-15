# Learner administration API

All endpoints require Sanctum authentication, an active organization membership selected by `X-Organization-Id` (or the member default), an active organization, and the relevant `learners.*` permission. Learners are resolved by public UUID only inside the active organization; an identifier from another organization returns `404`.

## Endpoints

| Method | Path | Permission |
|---|---|---|
| GET | `/api/v1/learners` | `learners.view` |
| POST | `/api/v1/learners` | `learners.create` |
| GET | `/api/v1/learners/{uuid}` | `learners.view` |
| PATCH | `/api/v1/learners/{uuid}` | `learners.update` |
| PATCH | `/api/v1/learners/{uuid}/academic-placement` | `learners.manage_academic_profile` |
| POST | `/api/v1/learners/{uuid}/status` | `learners.manage_status` |
| POST | `/api/v1/learners/{uuid}/archive` | `learners.archive` |
| POST | `/api/v1/learners/{uuid}/restore` | `learners.restore` |
| GET | `/api/v1/learners/{uuid}/status-history` | `learners.view_status_history` |

Creation always uses the trusted organization context, produces a profile-only learner with portal access disabled, and generates the learner number through `LearnerNumberService`. Supplying `learner_number` additionally requires `learners.override_number`.

Academic placement references must belong to the learner organization. The year must not be archived; curriculum, grade, and class must be active; and class-to-grade, class-to-year, grade-to-year, and grade-to-curriculum compatibility is enforced.

## Directory query

`search` matches first, last and preferred names, learner number, and admission number. Filters are `learner_status`, `onboarding_status`, `academic_year_id`, `curriculum_id`, `grade_id`, `class_id`, `portal_access_enabled`, `archived`, `admission_date_from`, and `admission_date_to`. Archived learners are excluded by default; use `archived=true` to return only archived profiles.

Allowed `sort` values are `learner_number`, `first_name`, `last_name`, `admission_date`, `learner_status`, and `created_date`; `direction` is `asc` or `desc`. Pagination uses `page` and `per_page`, defaults to 25, and is capped at 100.

Status, archive, and restore requests require a reason. Status history is immutable, newest first, and exposes the previous/new status, safe actor identity, reason, and timestamp. There are no history write endpoints.

Learner accounts, memberships, invitations, portals, guardians, imports, documents, consent, enrolment history, attendance, assessments, reports, and AI are outside this API.
