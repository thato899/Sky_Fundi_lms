# Attendance

Attendance owns organization-scoped sessions and preserved learner entries. Sessions move from `draft` to `open` when a complete register is saved, then to `finalized`; finalized entries cannot be edited. Users with `attendance.reopen` may reopen with a mandatory reason. Cancellation preserves every entry.

Eligibility uses trusted current placement: learners in the session class whose status is `admitted` or `active`. All other statuses are excluded. With no historical enrollment model, historical registers reflect placement when created; no attendance is invented or backfilled.

Routes are documented in `routes/`. Summaries only use finalized recorded sessions. The Reports module snapshots finalized-session counts inside explicit reporting-period dates and does not infer an attendance percentage. CSV excludes notes, guardian and audit data and neutralizes formulas. Scheduling can explicitly create one draft session per lesson through the existing service; it inherits academic context, never marks learners present, cancels only editable sessions, and preserves finalized attendance. Staff attendance records remain unsupported. Biometrics, location/QR tracking, automatic/self check-in, notifications, mobile/offline workflows, payroll, discipline, and AI prediction are excluded.

## Implementation inventory

- **Responsibilities:** learner attendance sessions, register entries, lifecycle, factual summaries/reports, CSV, and explicit lesson linkage.
- **Database/models:** `attendance_sessions` and `attendance_entries`; `AttendanceSession` and `AttendanceEntry`.
- **Services:** `AttendanceSessionService` and `AttendanceRecordingService`.
- **Policies:** `AttendanceSessionPolicy`, registered by the provider.
- **Controllers/routes:** `AttendanceController`, `AttendanceWebController`, organization resource middleware, `/api/v1/attendance`, and Blade `/attendance` routes.
- **Permissions/events:** nine seeded permissions; no module event classes.
- **Dependencies:** Organizations/Identity/RBAC, Academics, Learners, Staff, with Reports consumption and explicit Scheduling integration.
- **Testing:** `AttendanceManagementTest` covers creation/recording/lifecycle, authorization, isolation, reports, CSV, and lesson integration regressions.
- **Known limitations/future roadmap:** eligibility uses current placement; staff attendance, automation/biometrics, notifications, portals, offline/mobile, payroll, and AI remain unimplemented.
