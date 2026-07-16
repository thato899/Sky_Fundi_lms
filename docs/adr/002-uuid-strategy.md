# ADR-002: UUID strategy

Status: Accepted

## Decision

Use application-generated UUID string primary keys for platform and module entities, normally through `HasUuidPrimaryKey`; foreign keys use the exact referenced type.

## Consequences

Public identifiers are non-sequential and portable. Storage/indexes are larger than integers, and UUIDs never replace organization scoping, authorization, or database constraints.
