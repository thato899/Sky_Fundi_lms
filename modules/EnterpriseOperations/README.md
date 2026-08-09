# Enterprise Operations

This optional module adds enterprise-only operational capabilities without changing the single-school data model.

## Multi-campus rollout

The `enterprise.multi_campus` feature flag is disabled by default. Campus records are additive and are not required by existing Learners, Staff, Academics, Attendance, Assessments, Reports, or Scheduling records. A future migration may add nullable `campus_id` references only after the organization has a verified campus strategy and backfill plan.

Enabling the flag permits campus administration; disabling it leaves data intact but prevents new campus operations. No existing school is converted automatically.
