# Learner management

The Blade interface is available at `http://localhost:8000/learners` after an authenticated member selects an active organization. It uses server-side organization context; `organization_id` query and form values cannot select or change the organization.

`learners.view` opens the directory and profiles. The directory searches learner/admission numbers and first, last, and preferred names; filters status, onboarding, active organization-owned academic placement, portal access, archive state, and admission dates; supports approved learner-number/name/date/status/created sorts; and preserves query parameters through server-side pagination.

Creation requires `learners.create` and creates only a learner profile. Learner number generation uses the existing sequence service; an optional manual number is available only with `learners.override_number`. User, membership, login credentials, and portal access are not created. Identity/contact editing requires `learners.update`; current placement requires `learners.manage_academic_profile` and validates active same-organization year, curriculum, grade, and class compatibility.

Status changes require `learners.manage_status` and show transitions supplied by the status service. Archive and restore use POST forms with confirmation and require `learners.archive` or `learners.restore`. Immutable newest-first history is shown with `learners.view_status_history`. Foreign learner UUIDs return `404`, and unavailable actions are not rendered.

Manual smoke test:

```bash
make up
docker compose exec app php artisan route:list --path=learners
```

Open `/login`, select an organization at `/access` when prompted, then use the Learner management card on `/dashboard`.

The learner detail view includes linked guardians, primary/emergency/pickup and communication flags, relationship removal, a bounded consent summary, and consent recording. `/guardians` provides a searchable guardian directory plus create, detail, edit, linked-learner, and archive screens. A guardian profile never creates portal credentials. Existing invited/active organization identities may be linked only through the supported identity workflow.

Configured learner licence capacity is enforced during creation and restoration; validation feedback is returned without partial writes. Archived learners and guardians are excluded from normal directories. Guardian archival deactivates active relationships without deleting historical rows.

Bulk import, documents, historical enrolment, automatic guardian invitations, credential creation, and broader legal-compliance automation remain excluded.
