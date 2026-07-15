# Learners

The Learners module provides a secure organization-scoped administration API, learner profile foundation, atomic learner-number generation, and validated learner-status history.

Each profile belongs to an organization. A learner may exist as a profile only; links to a platform `User` and organization `Membership` are nullable. Optional current-placement relationships connect a profile to an academic year, grade, class, and curriculum.

Placement validation resolves all four academic references inside the learner's organization, rejects inactive or archived records, and preserves curriculum/grade/class/year compatibility. Academic records from another organization cannot be assigned even when their UUIDs are known.

`LearnerNumberService` allocates numbers from a row-locked sequence owned by each organization and optional academic year. Prefix and padding are configurable at generation time: the default is `LRN-000001`, while a prefix of `STU`, academic year `2026/27`, and padding of four produces `STU-2026/27-0001`. It never derives identifiers from learner counts or randomness. Existing generated numbers are skipped, and the database continues to enforce organization-scoped learner-number uniqueness.

Manual numbers must pass through `LearnerNumberService::validateManual()` before profile creation. Validation trims the value, enforces its storage length, and rejects a number already used by the organization; the database unique constraint remains the final concurrency safeguard.

`LearnerStatusService` validates transitions and records an immutable history row containing the previous status, new status, organization actor, reason, and change timestamp. Any non-archived status may be archived. Restoration is allowed only for an archived learner and returns the learner to the status captured immediately before archival. Every status write locks the learner row and runs with its history insert in one transaction.

Supported ordinary transitions are:

- `pending` to `admitted`;
- `admitted` to `active` or `withdrawn`;
- `active` to `temporarily_inactive`, `suspended`, `withdrawn`, `transferred`, or `completed`;
- `temporarily_inactive` to `active` or `withdrawn`;
- `suspended` to `active`, `withdrawn`, or `transferred`.

No-op transitions and other transitions are rejected. Terminal statuses can still be archived and subsequently restored to that terminal status.

## Administration API

The versioned endpoints under `/api/v1/learners` support directory search, filters, whitelisted sorting, pagination, profile-only creation, profile and current-placement updates, status transitions, archive/restore, and newest-first immutable status history. See [`docs/api/learners.md`](../../docs/api/learners.md) for the endpoint and query contract.

Every request requires authentication, an active organization membership, an active organization, the Learners module enabled for that organization, and the action-specific `learners.*` permission. The active organization comes only from trusted organization context. A dedicated middleware resolves the public learner UUID within that organization before controller execution, so cross-organization identifiers return `404`.

`LearnerService` coordinates writes and audits through the existing numbering, status, and audit services. Creation never creates a User or Membership and always disables portal access. Manual numbers require `learners.override_number`. `LearnerDirectoryService` eager-loads current placement and allows only documented search, filter, sort, and page-size inputs; archived learners are excluded unless `archived=true` is explicit.

Run the idempotent permission seeder after installing the module:

```bash
docker compose exec app php artisan db:seed --class="Modules\\Learners\\Database\\Seeders\\LearnersPermissionSeeder"
```

This grants all learner permissions to Super Admin and Organization Administrator, and all except number override to Academic Administrator. Teacher, Tutor, and Learner receive no learner-administration permissions by default. Authorization checks permissions rather than role names.

Learner login accounts, invitations, portal workflows, guardians, imports, documents, consent, attendance, homework, assessments, marks, reports, historical enrolments, RAG/AI features, Blade UI, mobile functionality, and the neighboring milestones are explicitly not implemented.
