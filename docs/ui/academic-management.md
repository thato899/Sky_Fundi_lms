# Academic management

The responsive Blade interface is available at `http://localhost:8000/academics` after an authenticated member selects an active organization. It covers academic years, nested terms, curricula, grades, classes, departments, subjects, timetable periods, and nested calendar entries.

Routes use authentication, account-lock protection, trusted organization context, Academics organization enforcement, and existing `academics.*` permissions. Ownership never comes from input. UUIDs resolve inside the active organization; nested terms and calendar entries must also belong to their year. Related years, grades, curricula, and departments are server-validated in the same organization, and class year must match grade year.

Setting a current year or term uses existing service semantics. Lifecycle, activation, reorder, and delete actions use CSRF-protected non-GET forms. Timetable periods have no delete action because the backend does not support it. Existing education settings are platform-global Core Settings, so the interface documents the limitation and does not expose a tenant edit form.

Timetable generation, staff/learner assignments, enrolment history, attendance, assessments, marks, reports, homework, lesson content, portals, AI/RAG, and mobile functionality are excluded.
