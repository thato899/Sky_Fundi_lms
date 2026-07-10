# /resources

**Purpose**: shared, platform-wide frontend resources — not owned by any single module.

**Responsibilities**: shared Blade layouts/components used across modules' views, platform-wide language files, and shared frontend build source (CSS/JS entry points) once introduced. Module-specific views/lang belong inside that module's own `resources/` folder (see [Module Anatomy](../docs/architecture/module-system.md#module-anatomy)), not here — keeping this folder here means a module can be removed cleanly without leaving orphaned shared-resource references.

**Allowed dependencies**: `/core` for branding/settings data consumed by shared layouts. Must not contain module-specific business logic or views.

**Future usage**: see [UI Conventions](../docs/ui/README.md).
