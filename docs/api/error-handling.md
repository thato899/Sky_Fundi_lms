# API Error Handling

## Standard Error Shape

```json
{
  "error": {
    "code": "validation_failed",
    "message": "The given data was invalid.",
    "details": {
      "email": ["The email field is required."]
    }
  }
}
```

## HTTP Status Codes

| Status | Meaning |
|---|---|
| 400 | Malformed request |
| 401 | Not authenticated |
| 403 | Authenticated but not authorized (RBAC denial) |
| 404 | Resource not found |
| 409 | Conflict (e.g. duplicate resource, state conflict) |
| 422 | Validation failed |
| 429 | Rate limited |
| 500 | Unhandled server error |
| 503 | Service temporarily unavailable (e.g. AI Gateway provider outage) |

## Error Codes

Error `code` values are stable, machine-readable, `snake_case` strings that clients can branch on (do not rely on parsing `message`, which is human-readable and may change wording). Each module defines its own module-prefixed error codes where generic ones don't fit, e.g. `attendance.register_already_closed`.

## Logging

All 5xx errors are logged through `core/Logging` with a correlation ID that is also returned to the client (`X-Request-Id` header) to make support/debugging traceable end-to-end. See [Logging Strategy](../development/README.md) and [Security → Audit Logs](../security/README.md) for the distinction between error logs and audit logs.

## Validation Errors

Always 422, always populate `details` keyed by field name, consistent with Laravel's default Form Request validation failure shape, so mobile/web clients can build one generic error-rendering path.
