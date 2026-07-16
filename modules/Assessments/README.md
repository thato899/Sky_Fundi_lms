# Assessments

Organization-owned assessment categories, assessments, complete atomic mark sheets, factual gradebooks and summaries, learner administrative result histories, and formula-safe CSV exports.

Assessments progress through draft/open, finalized/locked, and cancelled states. Reopening requires permission and a recorded reason. Release is separate from finalization and defaults to withheld. Marked results require a server-validated score and receive a server-calculated two-decimal percentage; pending, absent, excused, exempt, and not-submitted remain distinct and never imply zero.

Eligibility uses the learner's trusted current class and admitted/active status. Historical accuracy is therefore limited until enrolment history exists. Teacher-class and teacher-subject assignment are not implemented, so teacher/tutor permissions are conservative but cannot enforce an assignment boundary.

The separate Reports module consumes finalized results for immutable administrative report-card snapshots. Assessments may optionally reference a scheduled lesson through a nullable, non-cascading relationship; lesson cancellation never deletes an assessment and ordinary lessons never become examinations automatically. Promotion decisions, ranking, online examinations, question banks, submissions, guardian notifications, learner/guardian portals, AI grading, moderation, and mobile marking remain excluded.

## Implementation inventory

- **Responsibilities:** organization categories, assessment lifecycle, atomic result recording, gradebooks/summaries, release state, and safe CSV export.
- **Database/models:** `assessment_categories`, `assessments`, and `assessment_results`; `AssessmentCategory`, `Assessment`, and `AssessmentResult`.
- **Services:** `AssessmentCategoryService`, `AssessmentService`, and `AssessmentResultService`.
- **Policies:** `AssessmentPolicy` and `AssessmentCategoryPolicy`, registered by `AssessmentsServiceProvider`.
- **Controllers/routes:** category and assessment API controllers plus `AssessmentWebController`; `/api/v1/assessment-categories`, `/api/v1/assessments`, and Blade `/assessments` routes.
- **Permissions/events:** eleven seeded permissions in `module.json`; no module event classes are implemented.
- **Dependencies:** Organizations/Identity/RBAC plus Academics, Learners, Staff, and an optional Scheduling lesson link; Reports consumes finalized results.
- **Testing:** `AssessmentManagementTest` covers API/web workflows, permissions, isolation, validation, lifecycle, results, summaries, and export.
- **Known limitations/future roadmap:** current-placement and missing teacher-assignment limitations apply; portals, online exams, notifications, moderation, ranking/promotion, AI, and mobile are not implemented.
