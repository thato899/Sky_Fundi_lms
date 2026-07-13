# Identity and Organization Access

Identity keeps `User` as the platform authentication identity. `organization_memberships` is the single tenancy access record, connecting a user to an organisation, its organisation-scoped role, lifecycle status, invitation state, and default active organisation.

Every tenant-facing route should use `organization.context`. It resolves an active membership from `X-Organization-Id` or the member default and exposes `organization_membership` and `organization` request attributes. Permission resolution is membership role → permission → enabled module; disabled modules never grant their permissions.

The API exposes membership listing, invitation, acceptance/rejection, switching, and `/api/v1/identity/context`. Future modules must scope data by the resolved organization and must not add their own user-to-organization pivots.
