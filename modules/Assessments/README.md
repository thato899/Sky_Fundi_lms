# Assessments

Organization-owned assessment categories, assessments, complete atomic mark sheets, factual gradebooks and summaries, learner administrative result histories, and formula-safe CSV exports.

Assessments progress through draft/open, finalized/locked, and cancelled states. Reopening requires permission and a recorded reason. Release is separate from finalization and defaults to withheld. Marked results require a server-validated score and receive a server-calculated two-decimal percentage; pending, absent, excused, exempt, and not-submitted remain distinct and never imply zero.

Eligibility uses the learner's trusted current class and admitted/active status. Historical accuracy is therefore limited until enrolment history exists. Teacher-class and teacher-subject assignment are not implemented, so teacher/tutor permissions are conservative but cannot enforce an assignment boundary.

The separate Reports module consumes finalized results for immutable administrative report-card snapshots. Assessments may optionally reference a scheduled lesson through a nullable, non-cascading relationship; lesson cancellation never deletes an assessment and ordinary lessons never become examinations automatically. The quiz slice adds class-assigned questions, learner attempts, deterministic objective marking, structured AI suggestions, one AI regeneration, teacher draft/approval/override, versioned adaptive study plans, targeted revision and retesting, progress/mastery tracking, release notifications, released learner results and an equivalent restricted guardian result. AI recommendations never become official without teacher approval, released marking is read-only except for users with `quiz_submissions.override_released`, and study plans are generated through `AIManager` only after teacher-approved marking, falling back to a deterministic performance-based plan when no provider is reachable so release always yields a plan. Teachers may write an audited, editable report to the parent on a draft or published plan; it renders with the learner's released result and in the guardian portal.

The intervention dashboard keeps released-attempt detail but calculates overview averages once per unique learner. Its operational queue contains at most one row per learner and subject, choosing the highest current deterministic risk and then the latest release to break ties. Risk uses marks, plan completion, remaining concepts, revision results, inactivity and teacher adjustments; optional AI recommendations do not determine risk.

## Implementation inventory

- **Responsibilities:** organization categories, assessment lifecycle, atomic result recording, gradebooks/summaries, release state, and safe CSV export.
- **Database/models:** assessment management tables plus quiz questions, attempts, answers, versioned study plans, revision attempts, and AI request audit rows.
- **Services:** assessment category/result services, `QuizService`, and `StudyPlanService`.
- **Policies:** `AssessmentPolicy` and `AssessmentCategoryPolicy`, registered by `AssessmentsServiceProvider`.
- **Controllers/routes:** category and assessment API controllers plus `AssessmentWebController`; `/api/v1/assessment-categories`, `/api/v1/assessments`, and Blade `/assessments` routes.
- **Permissions/events:** eleven seeded permissions in `module.json`; no module event classes are implemented.
- **Dependencies:** Organizations/Identity/RBAC plus Academics, Learners, Staff, and an optional Scheduling lesson link; Reports consumes finalized results.
- **Testing:** `AssessmentManagementTest` covers API/web workflows, permissions, isolation, validation, lifecycle, results, summaries, and export.
- **Known limitations/future roadmap:** current-placement and missing teacher-assignment limitations apply; curated content-provider integrations, question banks, moderation, file responses, proctoring, and mobile offline revision are not implemented.
