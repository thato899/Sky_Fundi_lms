# ADR-009: Teaching assignments

Status: Proposed

## Decision

Authoritative teacher-to-class/subject assignments are recorded in an organization-owned `staff_teaching_assignments` table inside the Staff module, which owns the staff side and already consumes Academics structures. Each row links a staff profile to a class group, an optional subject, and an academic year, is date-ranged like `learner_enrolments` (ADR-008) with at most one open row per staff/class/subject tuple enforced by a generated-column unique constraint, and is maintained through a transactional `TeachingAssignmentService` that validates same-organization ownership and class/year cohesion. Academics remains upstream and gains no dependency on Staff. Consumer modules layer assignment checks on top of existing permission middleware: Assessments restricts quiz authoring, marking, and release to teachers assigned to the assessment's class; Attendance restricts session recording to teachers assigned to the session's class; Scheduling validates lesson staffing against assignments rather than free-form staff references. A platform-level administrative permission bypass is preserved so organization administrators keep full oversight, and enforcement is introduced behind a per-organization setting so existing organizations opt in without breaking current creator-ownership behaviour.

## Consequences

Teacher authorization becomes assignment-aware instead of permission-only, closing the gap recorded in the hackathon demo's known limitations, and the date-ranged history keeps past marking and attendance defensible after staff changes. Assessments, Attendance, and Scheduling gain a documented, tested contract on the Staff module, which must be listed in their manifests and READMEs. The opt-in setting means enforcement is not immediate platform-wide; removing the legacy creator-ownership path becomes possible only after organizations migrate. Bulk assignment tooling, timetable-driven assignment inference, and workload reporting remain out of scope.
