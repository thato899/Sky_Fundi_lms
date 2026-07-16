# /modules

Home of the implemented educational and operational modules: Academics, Organizations, Staff, Learners, Attendance, Assessments, Reports, and Scheduling. Future candidates are tracked in the [roadmap](../docs/roadmap.md).

**Purpose**: contain educational and operational bounded contexts outside platform Core.

**Responsibilities**: each subfolder here is one module, following the anatomy and manifest contract defined in [`/docs/architecture/module-system.md`](../docs/architecture/module-system.md). A module owns its own domain logic, database tables (prefixed with its module name — see [Database Conventions](../docs/database/conventions.md)), API routes, permissions, and tests.

**Allowed dependencies**: `/core`, including AI only through `core/AIGateway`, plus existing module relationships declared and documented by the owning modules. New hard dependencies require an explicit contract and must not be circular.

**Future usage**: each new module gets its own provider, manifest, README, owned migrations/routes/services/tests, and only the layer folders it needs.

**Built so far**: [`Academics`](Academics/README.md), [`Organizations`](Organizations/README.md), [`Staff`](Staff/README.md), [`Learners`](Learners/README.md), [`Attendance`](Attendance/README.md), [`Assessments`](Assessments/README.md), [`Reports`](Reports/README.md), and [`Scheduling`](Scheduling/README.md).

See also: [Module Development Guide](../docs/modules/module-development-guide.md).
