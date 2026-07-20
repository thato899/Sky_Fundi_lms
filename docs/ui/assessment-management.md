# Assessment management

`/assessments` provides the factual dashboard/directory. `/assessment-categories` manages organization configuration, `/assessments/create` creates and populates eligible learners, each assessment provides atomic mark capture and lifecycle/release actions, `/gradebook` is a paginated result list, `/assessment-reports` is factual marked-result reporting, and `/learners/{learner}/results` is administrative history.

Current placement is the eligibility source for roster materialization; enrolment history exists for report calculation, while assessment eligibility intentionally remains current-placement based. Release does not create learner or guardian portal output.
