# Organization administrator dashboard

`GET /dashboard` is the authenticated, read-only organization overview. Access requires an active account, an active membership in an active organization, trusted organization context, and the `organization.dashboard.view` membership-role permission. Organization Administrator and Academic Administrator roles receive the permission idempotently from the database seeders. Teacher, Tutor, Learner, and Guardian roles are not granted it by default.

For web sessions, the active organization is the server-side organization selected during login or at `/access`. Query-string and form organization identifiers do not establish dashboard context. API requests retain the existing trusted header/default-membership context behavior.

The dashboard reports live organization-scoped learner status and portal counts, staff employment counts, current academic year and academic catalog totals, active and invited membership counts, the latest organization license and subscription state, licensed-user capacity where defined, organization status, and safe recent activity. Setup gaps are calculated from organization branding, academic records, people records, licensing, subscriptions, and organization AI configuration.

Audit display is deliberately restricted to events directly targeting the active organization. Raw request metadata and before/after payloads are never rendered. Learner and staff additions are summarized from their organization-owned records. Empty data produces factual empty states.

The dashboard links only to implemented web routes: dashboard, trusted organization selection, and logout. Learner, staff, and academic management cards are disabled and identify their web interfaces as unavailable; existing APIs remain documented separately.
