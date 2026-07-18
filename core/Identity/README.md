# Identity and Organization Access

Identity keeps `User` as the platform authentication identity. `organization_memberships` is the single tenancy access record, connecting a user to an organisation, its organisation-scoped role, lifecycle status, invitation state, and default active organisation.

Every tenant-facing route should use `organization.context`. It resolves an active membership from `X-Organization-Id` or the member default and exposes `organization_membership` and `organization` request attributes. Permission resolution is membership role → permission → enabled module; disabled modules never grant their permissions.

The API exposes membership listing, invitation, acceptance/rejection, switching, and `/api/v1/identity/context`. Future modules must scope data by the resolved organization and must not add their own user-to-organization pivots.

Guardian onboarding extends this same membership record rather than creating a competing invitation table. Email-first pending memberships may temporarily have no `user_id`; their normalized invited email, SHA-256 token hash, expiry, delivery, resend, acceptance, and revocation metadata remain in Identity. Raw tokens exist only in the outbound acceptance URL. Acceptance atomically assigns the matching/new User, activates the membership, clears the token hash, and lets the Learners module link its intended guardian profile.
