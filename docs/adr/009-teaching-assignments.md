# ADR-009: Teaching assignments

Status: Accepted

## Decision

Authoritative teacher-to-class/subject assignments are recorded in an organization-owned `staff_teaching_assignments` table inside the Staff module, which owns the staff side and already consumes Academics structures. Each row links a staff profile to a class group, an optional subject (a subject-less row covers every subject in its class), and an academic year, is date-ranged like `learner_enrolments` (ADR-008) with open rows guarded by a generated-column unique index plus a row-locked duplicate check in the transactional `TeachingAssignmentService`, which also validates same-organization ownership and class/year cohesion. Academics remains upstream and gains no dependency on Staff. Consumer modules layer assignment checks at their service boundaries — Assessments and Attendance validate a referenced `staff_profile_id` against the assessment's or session's class and subject, and Scheduling validates lesson staffing — while the Assessments web layer additionally gates teacher marking, AI-suggestion, and release actions on the acting user's own assignment. Enforcement is opt-in per organization through the Organizations settings mechanism (group `staff`, key `enforce_teaching_assignments`; platform Core Settings has no organization scope), and the `teaching_assignments.bypass` permission preserves administrator oversight.

## Consequences

Teacher authorization becomes assignment-aware instead of permission-only, closing the gap recorded in the hackathon demo's known limitations, and the date-ranged history keeps past marking and attendance defensible after staff changes. Assessments, Attendance, and Scheduling exercise their already-declared Staff dependency through a documented, tested service contract. The opt-in setting means enforcement is not immediate platform-wide; the demo organization is seeded with enforcement on and the Mathematics teacher assigned. Assignment administration surfaces (web/API), bulk assignment tooling, timetable-driven assignment inference, and workload reporting remain out of scope.
