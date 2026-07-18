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
| GET/POST | `/api/v1/guardians` | `guardians.view` / `guardians.create` |
| GET/PATCH | `/api/v1/guardians/{uuid}` | `guardians.view` / `guardians.update` |
| POST | `/api/v1/guardians/{uuid}/archive` | `guardians.archive` |
| GET/POST | `/api/v1/guardians/{uuid}/invitations` | `guardians.view_invitations` / `guardians.invite` |
| POST | `/api/v1/guardians/{uuid}/invitations/{invitation}/resend` | `guardians.invite` |
| POST | `/api/v1/guardians/{uuid}/invitations/{invitation}/revoke` | `guardians.revoke_invitations` |
| GET/POST | `/api/v1/learners/{uuid}/guardians` | `learners.view` / `guardians.manage_relationships` |
| PATCH/DELETE | `/api/v1/learners/{uuid}/guardians/{relationship}` | `guardians.manage_relationships` |
| POST | `/api/v1/learners/{uuid}/guardians/consents` | `guardians.manage_relationships` |

Creation always uses the trusted organization context, produces a profile-only learner with portal access disabled, and generates the learner number through `LearnerNumberService`. Supplying `learner_number` additionally requires `learners.override_number`.

Academic placement references must belong to the learner organization. The year must not be archived; curriculum, grade, and class must be active; and class-to-grade, class-to-year, grade-to-year, and grade-to-curriculum compatibility is enforced.

## Directory query

`search` matches first, last and preferred names, learner number, and admission number. Filters are `learner_status`, `onboarding_status`, `academic_year_id`, `curriculum_id`, `grade_id`, `class_id`, `portal_access_enabled`, `archived`, `admission_date_from`, and `admission_date_to`. Archived learners are excluded by default; use `archived=true` to return only archived profiles.

Allowed `sort` values are `learner_number`, `first_name`, `last_name`, `admission_date`, `learner_status`, and `created_date`; `direction` is `asc` or `desc`. Pagination uses `page` and `per_page`, defaults to 25, and is capped at 100.

Status, archive, and restore requests accept an optional reason. Status history is immutable, newest first, and exposes the previous/new status, safe actor identity, reason, and timestamp. There are no history write endpoints.

Guardian profiles are organization-owned and may exist without a User. The invitation workflow creates an email-first pending Identity membership without credentials, queues a seven-day link, supports safe token rotation and revocation, and reports sent/expiry/accepted timestamps without exposing token hashes or internal identity IDs. Acceptance creates credentials only for a new invited email; an existing account must authenticate with the exact email. It then activates and links the same-organization membership transactionally. A linked guardian user can view only learners connected through an active relationship and cannot enumerate the learner directory.

Relationships support parent, legal guardian, caregiver, and other types, one current primary contact per learner, emergency/pickup flags, academic/financial communication preferences, and effective dates. Primary changes serialize on the learner and are also protected by a database uniqueness constraint. Inactive relationships are never primary. Removed relationships are restored in place when relinked so their stable identity and audit history are preserved without duplicate active rows.

Guardian portal visibility requires an active, non-archived guardian profile linked to the authenticated user, an active currently effective relationship, and an admitted/active/temporarily-inactive/suspended non-archived learner. The same centralized rule is used by API policy checks and the web learner view. Linked guardians see only their own relationship and never receive the administrative consent summary.

Consent is deliberately bounded to a typed status record, recorded/expiry dates, optional linked guardian and notes. General learner responses do not expose consent notes. Every consent write is audited.

An active organization license may define `max_learners`. Profile creation and restoration lock the current entitlement and reject writes that would exceed the count of non-archived learner profiles. A null limit remains unlimited.

Guardian lists exclude archived records by default. `status=archived` requires archive permission and returns only archived profiles. Active and inactive status filters remain archive-excluding.

Bulk import, documents, historical enrolment, automatic invitations on profile creation, and broader legal-compliance automation remain outside this API.
