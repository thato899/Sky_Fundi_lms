# ADR-003: Organization isolation

Status: Accepted

## Decision

Use a shared database with `organization_id` ownership. Resolve active context through authorized Identity membership; scope reads, writes, relations, uniqueness, route resolution, exports, and audits to it. Do not trust client-supplied ownership.

## Consequences

Users can belong to multiple organizations while records remain isolated. Jobs/commands need explicit context. Cross-organization support access must be an explicit audited Core capability, not an unscoped query.
