# Learners

The Learners module currently provides only the organization-scoped learner profile foundation: the `learner_profiles` table, `LearnerProfile` model, `LearnerStatus` enum, and a test factory.

Each profile belongs to an organization. A learner may exist as a profile only; links to a platform `User` and organization `Membership` are nullable. Optional current-placement relationships connect a profile to an academic year, grade, class, and curriculum.

`LearnerStatus` supports `pending`, `admitted`, `active`, `temporarily_inactive`, `suspended`, `withdrawn`, `transferred`, `completed`, and `archived`. This foundation does not define status transitions.

APIs, controllers, services, policies, invitations, portal workflows, guardians, imports, documents, attendance, assessments, marks, historical enrolments, learner-number sequences, RAG/AI features, and UI are explicitly not implemented.
