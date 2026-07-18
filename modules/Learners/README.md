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

## Web management interface

The authenticated interface at `GET /learners` uses the same directory, learner, numbering, status, policy, organization-context, and audit services as the API. It provides server-side search, organization-owned academic filters, whitelisted sorting, query-preserving pagination, profile-only creation, safe profile display, identity/contact editing, current-placement updates, valid status transitions, archive/restore, and newest-first immutable status history.

The active organization always comes from trusted web-session context. Learner UUIDs are resolved inside that organization, foreign academic values are rejected, and action links/forms are shown only for the applicable `learners.*` permission. Manual learner numbers appear only with `learners.override_number`; creation otherwise uses `LearnerNumberService`. Portal access remains disabled and no User or Membership is created.

## Administration API

The versioned endpoints under `/api/v1/learners` support directory search, filters, whitelisted sorting, pagination, profile-only creation, profile and current-placement updates, status transitions, archive/restore, and newest-first immutable status history. See [`docs/api/learners.md`](../../docs/api/learners.md) for the endpoint and query contract.

Every request requires authentication, an active organization membership, an active organization, the Learners module enabled for that organization, and the action-specific `learners.*` permission. The active organization comes only from trusted organization context. A dedicated middleware resolves the public learner UUID within that organization before controller execution, so cross-organization identifiers return `404`.

`LearnerService` coordinates writes and audits through the existing numbering, status, and audit services. Creation never creates a User or Membership and always disables portal access. Manual numbers require `learners.override_number`. `LearnerDirectoryService` eager-loads current placement and allows only documented search, filter, sort, and page-size inputs; archived learners are excluded unless `archived=true` is explicit.

Run the idempotent permission seeder after installing the module:

```bash
docker compose exec app php artisan db:seed --class="Modules\\Learners\\Database\\Seeders\\LearnersPermissionSeeder"
```

This grants all learner permissions to Super Admin and Organization Administrator, and all except number override to Academic Administrator. Teacher, Tutor, and Learner receive no learner-administration permissions by default. Authorization checks permissions rather than role names.

Guardian administration owns `guardian_profiles`, `learner_guardian_relationships`, and the deliberately bounded `learner_consents` records. Profiles do not imply identities: an optional identity link must reference an existing invited or active organization membership. Active relationships are many-to-many, organization-scoped, unique per learner/guardian pair, and permit one primary contact per learner. Primary replacement locks the learner row and is backed by a generated-column uniqueness constraint. Removed links are soft-deleted and restored in place on relink. Relationship writes, guardian archival, and consent writes are transactional and audited.

Guardian portal visibility is centralized in `GuardianPortalAccessService`: guardian and relationship must be active and current, neither may be archived/deleted, and the learner must be in a portal-visible non-archived lifecycle state. Administrative views may load all relationships and consent summaries; linked guardians receive only their own relationship and no consent data.

Guardian onboarding reuses the intended guardian profile’s Identity membership. Administrators with explicit invitation permissions can send, inspect, resend, or revoke a seven-day email invitation. Only a SHA-256 token hash is persisted; resend rotates the token and expiry, and accepted/revoked/expired tokens are unusable. New users are created only inside successful acceptance with the established password rules. Existing users authenticate as the exact invited email. Acceptance activates one organization membership and links its User and membership to the active, same-organization guardian profile transactionally. Mail is queued through Core Notifications and contains the organization name, expiry, support guidance, and URL but no learner or consent data.

An organization license may set `max_learners`. `LearnerCapacityService` locks the active entitlement and counts non-archived learner profiles before creation or restoration. Missing licenses and null limits preserve the existing unlimited behavior.

Administrative report-card history is provided by Reports. Bulk imports, documents, historical enrolments, broad compliance automation, homework, RAG/AI, and mobile remain explicit non-goals. Invitations are administrator-initiated; automatic invitations triggered by profile creation remain excluded. Attendance and Assessments continue to read trusted current placement.

## Implementation inventory

- **Responsibilities/tables/models:** learner and guardian administration, relationships, consent, numbering, placement and lifecycle/history across six owned tables.
- **Services:** learner directory/number/status/capacity services plus transactional `GuardianService`.
- **Policies:** `LearnerPolicy`, `GuardianPolicy`, and organization-scoped learner/guardian resolution middleware.
- **Controllers/routes:** versioned learner/guardian APIs and Blade management at `/learners` and `/guardians`.
- **Permissions/events:** nine permissions and `LearnerStatusChanged`, `LearnerArchived`, and `LearnerRestored`.
- **Dependencies:** Organizations, Identity, Users/RBAC/Audit, and Academics; Attendance, Assessments, and Reports consume learner data.
- **Testing:** nine Unit/Feature files cover schema, service behavior, numbering, status, directory/API/web management, isolation, and regressions.
- **Known limitations/future roadmap:** profiles remain identity-optional until an invitation is accepted; invitations are email-only and administrator-initiated; imports, documents, historical enrolment, AI, and mobile remain future work.
