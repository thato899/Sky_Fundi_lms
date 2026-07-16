# Scheduling permissions

Organization and Academic Administrators receive all Scheduling permissions idempotently. Super Admin receives them only in explicit organization context. Teacher/Tutor defaults are limited to view, explicit completion, and attendance creation. Learners and Guardians receive none.

Permissions are `scheduling.view`, `manage_periods`, `manage_calendar`, `manage_rooms`, `manage_templates`, `manage_lessons`, `assign_staff`, `materialize`, `reschedule`, `cancel`, `complete`, `override_conflicts`, `export`, and `create_attendance`, each with the `scheduling.` prefix.
