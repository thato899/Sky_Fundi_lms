# Academics API

All `/api/v1/academics` endpoints require authentication, an active organization membership and organization, and the existing action permission. The organization is selected by trusted context (`X-Organization-Id` or the member default), never by payload data.

Curricula, departments, academic years and terms, grades, classes, subjects, calendar entries, and timetable periods are listed and resolved only inside that organization. A foreign UUID returns `404`; foreign relationship references fail validation or a domain consistency rule. Create operations assign the active organization, and updates cannot change ownership.

Curriculum, department, and subject codes are unique per organization. Nested terms and calendar entries must match their route academic year. Grades, classes, and subjects may only reference academic records owned by the same organization. Super Admins use the same tenant endpoints with an explicit active membership; platform-wide unscoped catalog access is intentionally absent.
