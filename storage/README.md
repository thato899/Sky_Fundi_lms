# /storage

**Purpose**: runtime storage — logs, framework cache, compiled views, and (in local/dev environments) uploaded files.

**Responsibilities**: will hold Laravel's standard `app/`, `framework/`, and `logs/` subfolders once the application skeleton is committed. In production, file uploads are expected to go through `core/Storage`'s abstraction to cloud/object storage rather than relying on this local folder persisting across deployments/nodes (see [Deployment — Infrastructure Assumptions](../docs/deployment/environments.md#infrastructure-assumptions)).

**Allowed dependencies**: none — this is a runtime output location, git-ignored except for `.gitkeep` placeholders (see [`.gitignore`](../.gitignore)).

**Future usage**: standard Laravel storage layout.
