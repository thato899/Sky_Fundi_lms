# Attendance API

Authenticated organization-context routes provide session list/create/show/update, atomic register recording, finalization, reasoned reopening, cancellation, safe CSV export, learner history and factual summary. See `modules/Attendance/routes/api.php`. Foreign organization UUIDs resolve as 404 and finalized sessions are immutable until explicitly reopened.
