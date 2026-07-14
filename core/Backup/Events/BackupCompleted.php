<?php

declare(strict_types=1);

namespace Core\Backup\Events;

use Core\Backup\Application\DTOs\BackupResult;
use Core\Support\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

final class BackupCompleted implements Auditable
{
    use Dispatchable;

    /**
     * @param  BackupResult[]  $results
     */
    public function __construct(
        public readonly array $results,
    ) {}

    public function auditAction(): string
    {
        return 'backup.completed';
    }

    public function auditTarget(): ?Model
    {
        return null;
    }

    public function auditContext(): array
    {
        return ['after' => [
            'targets' => array_map(fn ($r) => ['target' => $r->target, 'success' => $r->success], $this->results),
        ]];
    }
}
