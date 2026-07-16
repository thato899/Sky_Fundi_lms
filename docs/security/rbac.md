# Role-based access control

Users receive organization membership context through Core Identity. Roles collect namespaced permissions and may be assigned to users/memberships through Core RBAC. Authorization checks effective permissions, not display role names.

Core permissions use names such as `core.users.manage`; module permissions use their declared namespace (`learners.view`, `attendance.record`, `reports.publish`, `scheduling.override_conflicts`). Idempotent seeders register permissions and default grants. Manifests describe the permission surface but do not themselves enforce it.

Enforcement is layered: route `permission` middleware, Form Request authorization, registered policies, resource-scoping middleware, and Application service invariants. Web buttons are not a security boundary. Active organization context is mandatory for tenant operations, including Super Admin routes that require it.

Implemented policy-backed modules are Organizations, Learners, Attendance, Assessments, Reports, and Scheduling. Academics and Staff use route/request/service checks rather than policy classes. See the permission-specific [Reports](report-permissions.md) and [Scheduling](scheduling-permissions.md) references and each module manifest/seeder for the exact list.
