# Scheduling API

All routes require authentication, trusted active organization context, active membership, enabled Scheduling module, and an action-specific `scheduling.*` permission. Resource UUIDs resolve inside the active organization and foreign UUIDs return 404.

`/api/v1/scheduling` provides paginated rooms, templates, entries, materialization, lessons, staff assignment, lifecycle actions, conflict inspection, attendance creation, and bounded CSV export. Academics retains timetable-period and calendar-entry endpoints.

Input uses the organization IANA timezone and concrete instants are stored in UTC. Conflicts use half-open ranges, so adjacency is allowed. Materialization is limited to 93 days and idempotent by template-entry/date. CSV excludes meeting URLs/private notes and neutralizes formulas.
