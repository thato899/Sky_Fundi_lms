# Enterprise Operations

Enterprise Operations is an optional organization module for high-risk, bulk
administration. It is deliberately separated from daily school operations so
single-school customers can keep their current workflow unchanged.

## Academic-year rollover

The academic-year rollover API follows a controlled workflow:

1. Create a dry run with a source year, destination year, and grade rules.
2. Review the persisted learner-level preview. No learner placement changes in
   this step.
3. Resolve blocked outcomes, optionally recording a manual placement and its
   reason.
4. Submit and obtain approval from someone other than the requester.
5. Execute the approved snapshot. New destination enrolments are stamped with
   the Enterprise Operation run identifier; source enrolments are retained as
   history.
6. Review the result and use rollback only while generated placements have not
   been subsequently changed or used.

Rules are keyed by source grade id. A rule can provide a
`destination_grade_id`, use `action: retain`, or use `action: complete` for
learners finishing the organisation's final grade. Missing destination grades
block approval; missing classes are visible warnings requiring operational
review.

### Required permissions

- `enterprise_operations.view` — inspect runs and evidence.
- `enterprise_operations.manage` — dry run, resolve, submit, execute, and
  request rollback.
- `enterprise_operations.approve` — approve a submitted rollover.

An organization administrator cannot approve their own rollover request.

### Administrator checklist

- [ ] Destination academic year exists.
- [ ] Destination grades and classes are active.
- [ ] Promotion, retention, and completion rules were reviewed.
- [ ] Dry run completed and blocking errors are zero.
- [ ] Manual placements and warnings were reviewed.
- [ ] A different authorized administrator approved the run.
- [ ] A backup is verified where required by local policy.
- [ ] Execution result and audit evidence were reviewed.

The organization feature/module enablement controls whether this operational
surface is available. Multi-campus data remains separately guarded by the
`enterprise.multi_campus` feature flag.
