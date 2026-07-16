# modules/Academics

**Purpose**: the reusable academic engine — academic years, terms, grades, classes, subjects, departments, curricula, academic calendar, and timetable foundation. This is **not** the School module: nothing here assumes the user belongs to a school, a tutoring centre, or any other organisation type. Per [Module System](../../docs/architecture/module-system.md), this is the first real module built in this repository.

**What's deliberately NOT here**: Schools, Teachers, Learners, Parents, Attendance, Homework, Assessments, Reports, AI Tutor, Messaging, Library, Sports, Finance. Those are later modules that will *depend on* Academics (e.g. an Attendance module registers attendance against a `ClassGroup` and a date, without needing to know anything about grades/curricula beyond that class's own relationships).

## Entities

| Entity | Model | Notable design choice |
|---|---|---|
| Curriculum | `Infrastructure/Models/Curriculum` | **Database-driven**, not an enum — CAPS/IEB/Cambridge/Custom are seed rows (`database/seeders/CurriculumSeeder.php`), so a new curriculum needs a database row, not a code change. |
| Department | `Infrastructure/Models/Department` | Same — database-driven (`database/seeders/DepartmentSeeder.php`). |
| Academic Year | `Infrastructure/Models/AcademicYear` | Exactly one `is_current` row, enforced by `Application/AcademicYearService::setCurrent()` — see that method's docblock. |
| Academic Term | `Infrastructure/Models/AcademicTerm` | Same "exactly one current" invariant, scoped per academic year. |
| Grade | `Infrastructure/Models/Grade` | Optionally tied to a Curriculum and/or a specific Academic Year. |
| Class | `Infrastructure/Models/ClassGroup` | Named `ClassGroup` because `Class` is a PHP reserved word — the table, routes, and API responses all still say "class". |
| Subject | `Infrastructure/Models/Subject` | Carries a reserved (currently unused) `ai_configuration` JSON column for a future per-subject AI tutor config resolved through `Core\AIGateway` — nothing reads/writes it yet. |
| Academic Calendar | `Infrastructure/Models/CalendarEntry` | One table, a `type` enum (School Day / Public Holiday / Exam Period / Assessment Period / Event) rather than five separate tables. |
| Timetable Period | `Infrastructure/Models/TimetablePeriod` | Reusable day/period/time/break building blocks only. **No scheduling/generation logic** — assigning a class or subject onto a period is future work. |

## Education Settings

Current academic year, current term, default curriculum, grading system, assessment rules, promotion rules, and timetable rules are **not** a new table — they're stored as a named group (`academics`) of `Core\Settings` rows via `Application/EducationSettingsService`, mirroring `Core\Branding`'s exact pattern on top of the same underlying service. `GET/PUT /api/v1/academics/settings`.

## Services

`AcademicYearService`, `AcademicTermService`, `GradeService`, `ClassService`, `SubjectService`, `CurriculumService`, `DepartmentService`, `CalendarService`, `TimetableService`, `EducationSettingsService` — one per entity family, each thin and audited. No Repository layer: this module uses Eloquent models directly from Application services, consistent with every existing Core service (see [Clean Architecture — Repository Pattern](../../docs/architecture/clean-architecture.md#why-repository-pattern-is-where-useful-not-mandatory-everywhere)); nothing here has query complexity that would justify one yet.

## Events

`AcademicYearCreated`, `AcademicYearClosed`, `AcademicTermCreated`, `GradeCreated`, `ClassCreated`, `SubjectCreated`, `CurriculumAssigned` (fired when a curriculum is attached to a Grade or Subject — not on Curriculum creation itself), `CalendarUpdated`. All implement `Core\Support\Contracts\Auditable` and are recorded automatically by `Core\AuditLogs\Listeners\AuditableEventSubscriber` — no manual `AuditLogService::record()` call needed for these; other mutations (updates, department assignment, reordering, term/year "set current") call `AuditLogService::record()` directly, matching the mix already used elsewhere in Core.

## Permissions

Eighteen permissions — a `.view` and `.manage` pair per resource family (`academic-years`, `terms`, `grades`, `classes`, `subjects`, `departments`, `curriculum`, `calendar`, `timetable`) — declared in `module.json`'s `provides.permissions` and registered through `Core\RBAC` by `database/seeders/AcademicsPermissionSeeder.php`. **No roles are created here** — per the brief, roles that combine these permissions (e.g. an eventual "Principal" or "Academic Coordinator" role) are a future module's or a tenant admin's concern, not this module's.

## Module Bootstrapping — Registry vs. Runtime

Two separate things both need to be true for this module to work, and it's worth being explicit about the difference:

1. **`Providers/AcademicsServiceProvider`** (registered in `bootstrap/providers.php`, exactly like every Core service) is what actually loads this module's migrations and routes into the running Laravel application — this is what makes the code *run*.
2. **`module.json`** + the `modules` database table (`Core\Modules\Application\ModuleManager`) is a separate *registry* tracking install/enable/disable state as data — see [Module System](../../docs/architecture/module-system.md). Nothing currently makes `ModuleManager::enable()`/`disable()` actually toggle whether `AcademicsServiceProvider` runs; that reconciliation (likely: the provider checking the registry before registering routes) is future work once a second module exists to prove the pattern out. For now, this module is unconditionally active once its ServiceProvider is registered, exactly like a Core service.

Run `php artisan db:seed --class="Modules\Academics\Database\Seeders\AcademicsDatabaseSeeder"` after migrating to seed permissions, curricula, and departments (not run automatically — see that seeder's own docblock).

## API

All routes are under `/api/v1/academics/...`, authenticated, and individually gated by the permissions above — see `routes/api.php`. Examples: `GET/POST /academic-years`, `POST /academic-years/{id}/set-current`, `GET/POST /academic-years/{id}/terms`, `GET/POST /grades`, `POST /grades/reorder`, `GET/POST /classes`, `GET/POST /subjects`, `PUT /subjects/{id}/curriculum`, `GET/POST /academic-years/{id}/calendar-entries`, `GET/POST /timetable-periods`.

## Web management

Authorized organization members can use `/academics` for academic years, nested terms, curricula, grades, classes, departments, subjects, timetable periods, and academic-year calendar entries. Writes delegate to the existing Application services. Trusted context supplies ownership, related UUIDs are organization-scoped, and invalid nested or foreign records return 404.

Current year and term actions retain existing service semantics. Year lifecycle and curriculum activation use explicit POST actions. Calendar deletion is supported; timetable-period deletion is not exposed because the backend does not implement it. Education settings remain platform-global Core Settings, so the web page documents the limitation without exposing an unsafe tenant form. Timetable generation, assignments, enrolment history, attendance, assessments, marks, reports, content, portals, AI, and mobile workflows remain excluded.

## Allowed Dependencies

`Core\Users`, `Core\Identity`, `Core\RBAC`, `Core\Settings`, `Core\AuditLogs`, `Core\Support`, `Core\Api`, and Organizations ownership. Learners, Staff, Attendance, Assessments, Reports, and Scheduling use established Academics relationships; Scheduling owns concrete templates and lessons.

## Organization ownership

All nine Academics entities are organization-owned operational data: curricula, departments, academic years, academic terms, grades, classes, subjects, calendar entries, and timetable periods. None is a platform-global reference entity. Codes for curricula, departments, and subjects are unique inside an organization and may be reused by another organization.

Authenticated APIs require trusted organization context. Lists, filters, relationships, model lookups, and UUID route resolution are scoped to it; foreign UUIDs return `404`, and payload `organization_id` values are never trusted. Related records must share ownership, including term/year, grade/curriculum/year, class/grade/year, subject/curriculum/department, and calendar/year relationships. Tenant Academics endpoints—including Super Admin calls—require an explicit active organization membership; cross-organization platform support access is not provided by these routes.

The upgrade migration adds nullable ownership first, backfills only when existing academic rows coexist with exactly one organization, then enforces non-null foreign keys. It fails with a clear error when ownership is ambiguous; operators must explicitly assign those rows before retrying. Rollback removes the scoped unique constraints, ownership foreign keys, and columns. Education settings still use the existing platform-global Core Settings store and are not academic entity records.

## Implementation inventory

- **Responsibilities/tables/models:** owns `academics_curricula`, `academics_departments`, `academics_academic_years`, `academics_academic_terms`, `academics_grades`, `academics_classes`, `academics_subjects`, `academics_calendar_entries`, and `academics_timetable_periods`, represented by the nine models listed above.
- **Services:** the ten Application services listed above own CRUD, lifecycle, relationship validation, ordering, calendar, periods, and settings orchestration.
- **Policies:** no Laravel policy classes; permission middleware/Form Request authorization, `EnforceAcademicOrganization`, scoped service lookups, and relationship validation enforce access.
- **Controllers/routes:** ten API controllers under `Http/Controllers/Api/V1`, `AcademicManagementController` for Blade, and the registered `routes/api.php` plus `routes/web.php` surfaces.
- **Permissions/events:** eighteen manifest permissions and eight auditable event classes, listed above.
- **Dependencies:** Core Auth/RBAC/Users/Identity/Settings/Audit/API/Support and Organizations ownership; Scheduling, Learners, Attendance, Assessments, Reports, and Staff consume academic records through established relationships.
- **Testing:** eight Feature/Unit test files cover years, calendars, grades/curricula, subjects/classes, web management, ownership migration, organization isolation, and service behavior.
- **Known limitations/future roadmap:** education settings remain platform-global; no timetable-period delete; historical enrolment, automated scheduling, portals, content, and AI workflows remain future work. Scheduling now owns template/lesson generation rather than Academics.
