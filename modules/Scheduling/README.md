# Scheduling

Scheduling owns organization rooms, reusable weekly timetable templates, concrete lessons, staff assignments, and immutable schedule-change history. It reuses Academics teaching periods and calendar entries rather than duplicating them. Organization-local input uses the validated IANA timezone stored on `organizations.timezone` (default `Africa/Johannesburg`); concrete instants are stored in UTC and converted only for presentation.

Conflicts use the half-open overlap rule `existing_start < proposed_end && existing_end > proposed_start`, so adjacent lessons do not conflict. Class, assigned staff, room, and active organization/grade/class closures are checked within the active organization. Cancelled and rescheduled-out lessons are ignored. Overrides require the dedicated permission and a recorded reason.

Active templates materialize only over an explicit range of at most 93 days. The source-entry/date unique constraint makes reruns idempotent. Closures and factual conflicts are skipped and counted. Rescheduling preserves the original and creates a linked replacement. Completed, cancelled, and rescheduled lessons are never hard-deleted. Cancelling a lesson cancels a linked editable attendance session; finalized attendance remains intact. Attendance creation is explicit, duplicate-safe, and never marks learners present.

The optional Assessment and Attendance foreign keys use `nullOnDelete`; schedule lifecycle changes never delete either record. CSV export is organization-scoped, date-bounded, formula-safe, and excludes meeting URLs and private lesson notes.

Not included: optimization or AI timetable generation, exam timetable generation, calendar synchronization, notifications, conferencing provisioning, public sharing, booking, portals, or mobile applications.
