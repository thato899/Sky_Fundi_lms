# Staff

Staff profiles are organization-scoped professional records linked to Identity memberships, never global User properties. The module reuses Academics departments by UUID, Identity invitations for account access, and RBAC membership roles. Every staff query uses the resolved organization context.

The organization Staff web interface is available at `GET /staff`. It supports server-side search, filters, sorting and pagination plus create, profile, edit, suspend and activate workflows. The web and API controllers share `StoreStaffRequest`, `UpdateStaffRequest`, and `StaffService`; organization ownership is always supplied by trusted middleware and is never accepted from a form. Foreign staff and department UUIDs resolve as `404` or fail scoped validation.

Organization Administrators receive the existing Staff permission set idempotently. Staff lists and profiles show only safe professional and membership summaries; audit before/after payloads and request metadata are not rendered.
