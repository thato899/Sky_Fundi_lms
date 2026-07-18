# AI-assisted quiz marking

Multiple-choice and true/false answers are marked deterministically. Written answers may receive a bounded AI suggestion, but only a teacher can make the final decision and release a result.

The Assessments module sends provider-neutral requests through `core/AIGateway`; it never calls OpenAI directly. The OpenAI adapter uses the Responses API with strict JSON Schema output. Requests contain only subject, grade level, question, marks, model answer, rubric/key concepts and learner answer. Names, addresses, guardian data and membership identifiers are excluded.

The response stores a suggested mark, rubric criteria, strengths, improvements, misconceptions, concise rationale, confidence and a required-review flag. Hidden reasoning is neither requested nor stored. Negative, excessive, mismatched or incomplete marks are rejected.

Teachers may save incomplete marking as a draft, approve complete bounded marks with non-empty feedback, regenerate each written-answer recommendation once, and release only an approved attempt. Release records the reviewing and releasing users and timestamps and notifies linked learner and guardian identities. Learner and guardian views expose only final marks and teacher-approved feedback; provider, model, confidence, rationale, and other AI metadata remain internal. Released marking is read-only unless the user has the explicit `quiz_submissions.override_released` permission.

Release also generates and publishes a structured adaptive study plan through `AIManager`. Plans are based only on teacher-approved weak concepts, rubric failures and bounded grading metadata. They include goals, schedules, easy/medium/challenge revision exercises, reflection, descriptive video/reading recommendations, duration, success criteria and a next-assessment recommendation. Explicit regeneration creates a new draft version; publishing supersedes but retains the previous version. Learner progress and AI-evaluated retests update mastery, while guardians see only published overall progress, completed work, remaining work and teacher comments.

Each request has an idempotency key, status, provider/model, token counts and estimated cost. Missing credentials, timeout, rate limits, malformed output or outage preserve the submission and leave it available for manual marking.

```dotenv
AI_DEFAULT_PROVIDER=openai
AI_OPENAI_ENABLED=true
AI_OPENAI_API_KEY=
AI_OPENAI_MODEL=gpt-5-mini
AI_OPENAI_TIMEOUT=20
AI_MARKING_ENABLED=true
AI_MARKING_MAX_OUTPUT_TOKENS=900
AI_MONTHLY_MARKING_ALLOWANCE=250
```

Never expose or commit the key. Cost values are configurable demo assumptions. Tests use HTTP fakes and make no live calls.
