# Report-card management interface

Authorized organization users open `/reports` for factual lifecycle counts, the active reporting period, eligible learners, and learners with a report. Navigation covers grading scales, reporting periods, safe templates, individual or grade/class batch generation, the searchable directory, report detail/review, version history, PDF, and CSV.

Every visible mutation is a CSRF-protected POST/PATCH action. Published reports are read-only; withdrawal preserves the snapshot and requires a reason. Batch generation uses one transaction per learner and reports success/failure counts. Learner administration links to authenticated administrative history; no learner or guardian portal is exposed.

PDFs render server-side with current organization branding and snapshotted academic content. Templates expose safe options only; no unrestricted markup or public real-learner preview exists.
