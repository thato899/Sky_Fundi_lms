# /modules

Home of every educational and operational feature module for the Sky Fundi Platform: Academics, Schools, Tutoring, Attendance, Homework, Assessments, AI-facing features built on the AI Gateway, Library, Sports, Transport, Finance, Messaging, Newsletters, Reports, Hostel, Clinic, Visitors, Inventory, and any future module.

**Purpose**: contain all domain/educational logic, fully isolated from Core and from each other.

**Responsibilities**: each subfolder here is one module, following the anatomy and manifest contract defined in [`/docs/architecture/module-system.md`](../docs/architecture/module-system.md). A module owns its own domain logic, database tables (prefixed with its module name — see [Database Conventions](../docs/database/conventions.md)), API routes, permissions, and tests.

**Allowed dependencies**: `/core` (via documented Core service interfaces only), the AI Gateway (via `core/AIGateway`, never a provider SDK directly). Modules must not depend on each other's internal classes — see [Cross-Module Communication](../docs/architecture/module-system.md#cross-module-communication).

**Future usage**: as each module in the [Roadmap](../docs/roadmap.md) is built, it gets its own folder here (e.g. `modules/Academics/`, `modules/Attendance/`) with its own `README.md` per the standard anatomy. Nothing is scaffolded here yet — this is the foundation stage.

See also: [Module Development Guide](../docs/modules/module-development-guide.md).
