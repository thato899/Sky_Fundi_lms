# Attendance

Attendance owns organization-scoped sessions and preserved learner entries. Sessions move from `draft` to `open` when a complete register is saved, then to `finalized`; finalized entries cannot be edited. Users with `attendance.reopen` may reopen with a mandatory reason. Cancellation preserves every entry.

Eligibility uses trusted current placement: learners in the session class whose status is `admitted` or `active`. All other statuses are excluded. With no historical enrollment model, historical registers reflect placement when created; no attendance is invented or backfilled.

Routes are documented in `routes/`. Summaries only use finalized recorded sessions. The Reports module snapshots finalized-session counts inside explicit reporting-period dates and does not infer an attendance percentage. CSV excludes notes, guardian and audit data and neutralizes formulas. Staff attendance records and teacher-class assignments are not currently supported. Biometrics, location/QR tracking, automatic/self check-in, notifications, mobile/offline workflows, payroll, assessments, discipline, and AI prediction are excluded.
