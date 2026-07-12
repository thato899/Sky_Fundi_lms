# core/Queue

**Purpose**: the platform's queue *taxonomy* — named queues every job, mailable, and notification dispatches onto, so worker capacity can eventually be sized and prioritised per concern. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**Deliberately not a queue driver or job-dispatch abstraction** — Laravel's own queue system (`config/queue.php`, `ShouldQueue`, `Illuminate\Bus\Queueable::onQueue()`) is used directly. This folder only fixes the *names* of the queues in one place instead of hardcoded strings scattered across every dispatch site.

**Responsibilities**:
- `Domain/QueueName` — `default | ai | reports | imports | exports | notifications | email | backups`, per the brief's job-queue-readiness list (`Future OCR`/`Future Video Processing` are intentionally not separate queues yet — they'd reuse `imports`/`exports` until real work exists to justify splitting them out).
- Consumers call `$this->onQueue(QueueName::Ai->value)` inside a `ShouldQueue` class's constructor — see `Core\Notifications\Infrastructure\Notifications\CoreNotification` for the pattern.

**Allowed dependencies**: none. Depended on by anything that queues work.

**Operating a worker per queue**: `php artisan queue:work --queue=ai,default` (comma-separated, first-listed gets priority). See [Deployment](../../docs/deployment/environments.md#infrastructure-assumptions) for the existing requirement that queue workers run as long-lived processes separate from web request handling.
