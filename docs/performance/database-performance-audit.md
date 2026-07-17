# Database performance audit

## Scope and method

This audit covers the executable platform at baseline `0f9f1fe` on
`perf/database-optimization`. The clean opening suite passed with **166 tests
and 922 assertions**. PHPUnit uses deterministic in-memory SQLite data; MySQL
is used by `make migrate-check` for migration, seed, rollback, and re-migration
validation. The completed suite passes with **167 tests and 924 assertions**.

Queries, relationships, resources, policies, middleware, services, migrations,
pagination, exports, and aggregate paths were inspected across `app/`, `core/`,
and every implemented module. Query logging confirmed the scheduling defect
described below. Wall-clock thresholds were deliberately not added.

The local MySQL developer database did not contain the application tables, so
ad-hoc `SHOW INDEX` and `EXPLAIN` commands against it failed with table-not-found.
Consequently, this milestone does not claim a measured MySQL plan change.
Historical migrations show the relevant indexes, and the isolated MySQL
migration lifecycle remains the schema verification gate.

## Findings by area

| Area | Critical paths, tables, and relationships | Existing query strategy and indexes | Finding and disposition |
|---|---|---|---|
| Organizations | Directory and configuration; `organizations`, settings, modules, administrators, AI configuration | Length-aware pagination; filtered repository query; unique code and `(status,type)` plus owned configuration uniqueness | **Acceptable current behavior.** Search contains leading wildcards and cannot use a normal B-tree efficiently; full-text/prefix search is deferred because it could change matching semantics. |
| Identity and authentication | Active membership, organization, role permissions, enabled modules; `organization_memberships`, organizations, roles, permissions | Context loads trusted membership relationships; membership has `(user_id,organization_id)` unique and `(organization_id,status)` index | **Potential future risk.** Relationship loading is bounded per request. Request-wide permission arrays reuse loaded relations; no cross-user/organization authorization cache was introduced. |
| Staff | Directory; `staff_profiles`, membership, user, department | Bounded pagination; web directory eager-loads required relations; `(organization_id,last_name,first_name)` and organization/employee uniqueness | **Acceptable current behavior.** API resource uses scalar columns only. Search/filter expansion should be measured before another composite index is added. |
| Learners | Directory and placement; `learner_profiles` and four academic relationships | Length-aware pagination capped by validation; eager loads placement; deterministic secondary ID order; organization/number uniqueness and placement indexes | **Acceptable current behavior.** Query count is bounded by eager loading. Multi-column directory indexes are deferred until production filter frequency/selectivity is known. |
| Academics | Year/term and catalog lists; academic tables and learner counts | Bounded web pagination, `withCount`, bulk grouped learner counts, organization scoping | **Acceptable current behavior.** Dashboard/catalog counts are separate constant-count aggregates, not N+1 queries. |
| Attendance | Session list, learner history, summaries; `attendance_sessions`, `attendance_entries` | Bounded pagination; session relations eager loaded; `withCount`; SQL `count`, `sum`, and grouped status totals; organization/date/status and learner/status indexes | **Acceptable current behavior.** CSV loads one explicitly selected register; background/chunk conversion is deferred until export volume and job contracts exist. |
| Assessments | Assessment list, gradebook, histories, statistics; assessment tables | Bounded pagination; list/gradebook relations eager loaded; `withCount`; SQL grouped counts/averages; organization-period/status and learner/status indexes | **Acceptable current behavior.** Summary uses a constant number of aggregate queries. Combining them would add database-specific conditional SQL for little demonstrated benefit. |
| Reports | Card list, generation preparation, PDF/CSV; report tables and assessment/attendance inputs | Bounded API pagination and eager-loaded card relations; generation uses bulk input collections to preserve grading semantics; scoped period/status indexes | **Deliberate trade-off.** Generation calculations remain in PHP where weighting, null, status, and decimal rules are domain-sensitive. CSV currently materializes its scoped result set; chunked streaming is deferred because it requires an explicit large-export/job contract. |
| Scheduling | Lesson overlap and template overlap; `scheduled_lessons`, `scheduled_lesson_staff`, rooms, template entries, calendar entries | Half-open overlap predicates; organization/date/status and class overlap indexes; staff pivot unique plus `(organization_id,staff_profile_id)` | **Confirmed defect and optimization implemented.** Staff conflict checks were N+1. Template candidates were also filtered after hydration. Both are corrected without changing overlap semantics. |
| Licensing and subscriptions | Latest organization entitlement and scheduled lifecycle sweeps; `licenses`, `subscriptions` | Status indexes; dashboard uses bounded latest lookup; sweep collections are operational batch paths | **Potential future risk.** Polymorphic organization lookup lacks a dedicated composite index, but current volume/selectivity was not measurable. An index is deferred rather than added speculatively. Scheduled sweeps should use chunking when production volume demonstrates need. |
| RBAC and permissions | Core role/direct grants and organization membership permissions | Core permission list is cached for 300 seconds with explicit service invalidation; organization permission resolver uses already loaded role/module relations | **Acceptable with security trade-off.** No new cache was added. Existing Core key is user-scoped; tenant authorization remains request-scoped so organization switches cannot reuse another tenant's result. |
| Dashboards and analytics | Organization dashboard counts/activity; analytics metric summaries | Constant number of SQL aggregates; limited recent activity; analytics groups in SQL and has `(metric,recorded_at)` | **Potential future risk.** Dashboard query count is constant but relatively high. A conditional-aggregate rewrite or short request cache is deferred until endpoint query baselines show material value. |
| Exports and background-capable workloads | Attendance, assessment, report, and scheduling CSV; notifications/queue | Tenant-scoped exports; formula sanitization; scheduling export is date bounded; notifications are queued | **Deliberate trade-off.** Existing API contracts are synchronous. No caching or new job workflow was introduced. Large report export materialization is the clearest deferred risk. |
| Shared Core services | Settings, branding, feature flags, audit, modules, health, analytics | Settings bulk reads; feature flags cache keyed by flag and scope; bounded audit pagination; provider/module definitions are small | **Acceptable current behavior.** No high-value low-volatility read was confirmed to justify another cache and invalidation surface. |

## Measured scheduling defect

The deterministic regression fixture creates five overlapping lessons for one
organization and assigns the same staff member to each. It proposes a contained
interval (`09:15–09:45`) inside the existing `09:00–10:00` lessons.

Before the correction, the query shape was:

1. one query for overlapping lessons;
2. one staff `exists` query for every overlapping lesson;
3. one calendar-closure query.

That is `2 + N`: **7 SELECTs for five lessons**, and it grows linearly.

After the correction, Eloquent emits the staff match as a correlated
`withExists` subquery in the overlapping-lesson query, followed by the one
closure query: **2 SELECTs for five lessons**. The fixture returns all ten
expected class/staff conflict details, proving result semantics are preserved.
The query-count assertion is hardware-independent.

Template-entry conflict filtering now applies class/room predicates in SQL
instead of hydrating unrelated overlapping entries. Query count was already
constant, so this is recorded as reduced hydration, not an N+1 fix.

The applicable historical indexes are:

- `scheduled_lessons (organization_id, lesson_date, status)`;
- `scheduled_lessons (organization_id, class_id, starts_at, ends_at)`;
- unique `scheduled_lesson_staff (scheduled_lesson_id, staff_profile_id)`;
- `scheduled_lesson_staff (organization_id, staff_profile_id)`;
- `timetable_template_entries (organization_id, weekday, start_time, end_time)`;
- `academics_calendar_entries (organization_id, start_date, end_date, affects_teaching)`.

No index was added: the correlated staff lookup uses the pivot unique index's
leftmost lesson ID and staff ID, and redundant indexing would add write and
storage cost without supporting a new access path.

## N+1, pagination, aggregation, and cache conclusions

Resource collections that dereference relationships were checked against their
controller/service queries. Attendance, Assessments, Reports, Learners,
Academics, Scheduling, audit, and RBAC collection paths already eager-load or
aggregate the relations they serialize. The only confirmed collection-sized
query growth was scheduling staff conflict detection.

Normal API pagination contracts were preserved. Page-size validation/clamping
remains in place. No cursor pagination was introduced because it would alter
response metadata. No SQL aggregate replaced grading or report-card domain
calculations, and no cache was introduced. Avoiding new caches keeps tenant,
authorization, attendance, and scheduling invalidation behavior unchanged.

## Compatibility, isolation, and deferred work

The optimization preserves the half-open overlap rule, ignored lesson
statuses, closure rules, conflict detail shape, public identifiers, status
codes, and response structures. Both lesson and closure queries retain explicit
`organization_id` predicates; the staff subquery is correlated to lessons
already selected inside that organization. Existing foreign-organization tests
remain authoritative.

Deferred items require production cardinality/selectivity evidence or a larger
compatible workflow:

- organization and learner leading-wildcard search strategy;
- polymorphic license/subscription lookup indexes;
- conditional aggregation for dashboard metrics;
- chunked/background report-card exports;
- scheduled lifecycle sweep chunking;
- MySQL `EXPLAIN ANALYZE` with representative production-like cardinalities;
- broader endpoint query-count fixtures for every module.

These are risks or opportunities, not completed improvements.
