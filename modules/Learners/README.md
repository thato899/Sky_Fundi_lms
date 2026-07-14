# Learners

The Learners module provides the organization-scoped learner profile foundation, atomic learner-number generation, and validated learner-status history.

Each profile belongs to an organization. A learner may exist as a profile only; links to a platform `User` and organization `Membership` are nullable. Optional current-placement relationships connect a profile to an academic year, grade, class, and curriculum.

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

APIs, controllers, policies, invitations, portal workflows, guardians, imports, documents, attendance, assessments, marks, historical enrolments, RAG/AI features, and UI are explicitly not implemented.
