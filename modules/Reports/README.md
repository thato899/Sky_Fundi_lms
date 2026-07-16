# Reports

Reports owns organization grading scales, reporting periods, safe display templates, versioned report-card snapshots, lifecycle comments, professional PDF rendering, and formula-safe administrative CSV exports.

Calculations use only the learner's current organization/year/term/class/grade, assessment dates inside the reporting period (or cutoff), finalized assessments, and marked numeric results. Finalized but administratively withheld results contribute to internal generation because release is separate from finalization and no learner/guardian portal exists. Draft/open/cancelled assessments are excluded. Absent, excused, exempt, pending, and not-submitted results are never zero.

If all valid marked results for a subject are unweighted, the result is their ordinary mean. If all are weighted, weights must total exactly 100%; a total below 100% or mixed weighted/unweighted input is `insufficient_data`, and a total above 100% is rejected. Overall average is the ordinary mean of calculated subjects only. Bands use inclusive two-decimal ranges and must continuously cover `0.00–100.00`, for example `0.00–49.99` then `50.00–100.00`.

Attendance counts only entries from recorded finalized sessions inside the period. It snapshots session, present, absent, late, excused, and remote counts. No attendance percentage is inferred.

Reports move `generated → under_review → approved → published → withdrawn`. Withdrawal requires a reason. Published and withdrawn snapshots are immutable. Regenerating a generated draft replaces its subject rows; regenerating a published/withdrawn report creates the next learner/period version under a transaction and row lock. Subject names/codes, resolved grade labels/symbols, calculations, placement, attendance, and comments are preserved. Organization branding is intentionally current-at-render rather than snapshotted.

Templates accept only whitelisted booleans, plain footer text, and `A4`/`LETTER`; arbitrary HTML, CSS, JavaScript, and file paths are not accepted. Comments are plain text and escaped. Administrative/principal comment types require approval permission. Teacher assignment data does not exist, so assignment enforcement is not claimed.

This module does not implement promotion, progression, ranking, AI comments/decisions, portals, notifications, transcripts, certificates, electronic signatures, payment gating, or mobile workflows. Historical accuracy is limited by current placement because historical enrolment is not implemented.
