# ADR-008: Historical enrolment

Status: Accepted

## Decision

Learner placement history is recorded in an organization-owned `learner_enrolments` table inside the Learners module. Every placement write through `LearnerService` reconciles the timeline in the same transaction: when the placement tuple changes, the open row is closed and a new date-ranged row snapshots the academic year, grade, class, and curriculum, with at most one open row per learner enforced by a generated-column unique constraint. Existing placements were backfilled as initial open rows, and a placement change that finds no open row records the superseded placement as a closed row so learners created outside the service keep a complete timeline. Current-placement columns on `learner_profiles` remain the authoritative present and continue to drive roster materialization in Attendance and Assessments; Reports resolves the classes a learner occupied during a reporting window through `LearnerEnrolmentService` and unions the current class so calculation is strictly additive over the previous behaviour.

## Consequences

Report-card calculation now includes finalized results from every class the learner occupied during the period, so mid-period moves no longer hide history, and the former grade filter is removed because class membership already implies the grade. Rosters materialized by Attendance and Assessments remain point-in-time snapshots taken at creation, which was already placement-faithful. Enrolment rows are maintained only through `LearnerService`, carry the acting user where known, and are closed rather than rewritten, preserving an auditable timeline. Enrolment history API/web surfaces, historical placement corrections, and promotion workflows remain out of scope.
