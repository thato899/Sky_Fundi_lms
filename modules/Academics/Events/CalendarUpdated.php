<?php

declare(strict_types=1);

namespace Modules\Academics\Events;

use Core\Support\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Academics\Infrastructure\Models\CalendarEntry;

final class CalendarUpdated implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly CalendarEntry $entry,
        public readonly string $action,
    ) {}

    public function auditAction(): string
    {
        return 'academics.calendar.updated';
    }

    public function auditTarget(): ?Model
    {
        return $this->entry;
    }

    public function auditContext(): array
    {
        return ['after' => ['action' => $this->action, 'type' => $this->entry->type->value, 'name' => $this->entry->name]];
    }
}
