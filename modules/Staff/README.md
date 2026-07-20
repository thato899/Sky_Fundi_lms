# Staff

Staff profiles are organization-scoped professional records linked to Identity memberships, never global User properties. The module reuses Academics departments by UUID, Identity invitations for account access, and RBAC membership roles. Every staff query uses the resolved organization context.

The organization Staff web interface is available at `GET /staff`. It supports server-side search, filters, sorting and pagination plus create, profile, edit, suspend and activate workflows. The web and API controllers share `StoreStaffRequest`, `UpdateStaffRequest`, and `StaffService`; organization ownership is always supplied by trusted middleware and is never accepted from a form. Foreign staff and department UUIDs resolve as `404` or fail scoped validation.

Organization Administrators receive the existing Staff permission set idempotently. Staff lists and profiles show only safe professional and membership summaries; audit before/after payloads and request metadata are not rendered. Scheduling assigns lessons only to valid active staff profiles, supports one primary teacher plus co-teaching roles, and filters timetables by staff without exposing private staff data.

Teaching assignments (ADR-009) record which staff member teaches which class and subject in `staff_teaching_assignments`. Rows are date-ranged like learner enrolments, carry the acting user, and are maintained only through the transactional `TeachingAssignmentService`, which validates same-organization ownership, class/year cohesion, and active employment, and closes rather than rewrites history. A subject-less row covers every subject in its class. Enforcement is opt-in per organization (Organizations settings group `staff`, key `enforce_teaching_assignments`): when enabled, Assessments and Attendance reject staffing references without a covering assignment, Scheduling validates lesson staffing, and teacher marking/release actions in Assessments require the acting user's own assignment unless the membership carries `teaching_assignments.bypass`. There is no assignment web/API surface yet; assignments are created by services and seeders.

## Implementation inventory

- **Responsibilities:** organization professional profiles, membership/department linkage, employment status, directory and management UI/API.
- **Database/models:** `staff_profiles`/`StaffProfile` and `staff_teaching_assignments`/`TeachingAssignment`.
- **Services:** `StaffService` and `TeachingAssignmentService`.
- **Policies:** no module policy class; permission middleware/Form Requests, service ownership checks, and trusted organization context enforce access.
- **Controllers/routes:** API and Blade `StaffController` classes under `/api/v1/staff` and `/staff`.
- **Permissions/events:** fifteen seeded permissions including `teaching_assignments.view/manage/bypass`; no module event classes.
- **Dependencies:** Organizations, Identity memberships, Users/RBAC/Audit/Storage/Notifications, and Academics departments; Assessments, Attendance, and Scheduling consume staff profiles and teaching assignments through `TeachingAssignmentService`.
- **Testing:** `StaffManagementWebTest` covers management, validation, permissions, status, department scoping, and cross-organization protection; `TeachingAssignmentTest` covers assignment lifecycle, subject/date resolution, enforcement gating, and the actor/bypass gate.
- **Known limitations/future roadmap:** document and invitation/role capabilities are permission foundations rather than complete UI workflows; assignment web/API administration, bulk assignment, payroll, staff attendance, portals, and mobile are not implemented here.
