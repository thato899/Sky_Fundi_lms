# Staff

Staff profiles are organization-scoped professional records linked to Identity memberships, never global User properties. The module reuses Academics departments by UUID, Identity invitations for account access, and RBAC membership roles. Every staff query uses the resolved organization context.

The organization Staff web interface is available at `GET /staff`. It supports server-side search, filters, sorting and pagination plus create, profile, edit, suspend and activate workflows. The web and API controllers share `StoreStaffRequest`, `UpdateStaffRequest`, and `StaffService`; organization ownership is always supplied by trusted middleware and is never accepted from a form. Foreign staff and department UUIDs resolve as `404` or fail scoped validation.

Organization Administrators receive the existing Staff permission set idempotently. Staff lists and profiles show only safe professional and membership summaries; audit before/after payloads and request metadata are not rendered. Scheduling assigns lessons only to valid active staff profiles, supports one primary teacher plus co-teaching roles, and filters timetables by staff without exposing private staff data.

## Implementation inventory

- **Responsibilities:** organization professional profiles, membership/department linkage, employment status, directory and management UI/API.
- **Database/models:** `staff_profiles` and `StaffProfile`.
- **Services:** `StaffService`.
- **Policies:** no module policy class; permission middleware/Form Requests, service ownership checks, and trusted organization context enforce access.
- **Controllers/routes:** API and Blade `StaffController` classes under `/api/v1/staff` and `/staff`.
- **Permissions/events:** thirteen seeded permissions; no module event classes.
- **Dependencies:** Organizations, Identity memberships, Users/RBAC/Audit/Storage/Notifications, and Academics departments; Scheduling consumes valid staff profiles.
- **Testing:** `StaffManagementWebTest` covers management, validation, permissions, status, department scoping, and cross-organization protection.
- **Known limitations/future roadmap:** document and invitation/role capabilities are permission foundations rather than complete UI workflows; payroll, staff attendance, assignment administration, portals, and mobile are not implemented here.
