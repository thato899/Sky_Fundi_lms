<?php

declare(strict_types=1);

namespace Modules\EnterpriseOperations\Application;

use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Modules\EnterpriseOperations\Infrastructure\Models\EnterpriseOperationRun;
use Modules\Organizations\Infrastructure\Models\Organization;

/**
 * Safe workflow boundary for bulk imports and academic rollover. Preview is
 * immutable evidence; execution must follow approval; rollback is recorded
 * and only permitted for an executed operation with an undo contract.
 */
final class EnterpriseOperationService
{
    public function preview(Organization $organization, string $type, array $payload, User $actor, array $preview): EnterpriseOperationRun
    {
        return EnterpriseOperationRun::query()->create(['organization_id' => $organization->getKey(), 'operation_type' => $type, 'status' => 'pending_approval', 'payload' => $payload, 'preview' => $preview, 'requested_by' => $actor->getKey()]);
    }

    public function approve(EnterpriseOperationRun $run, User $actor): EnterpriseOperationRun
    {
        if ($run->getAttribute('status') !== 'pending_approval') {
            throw new DomainException('Only a preview awaiting approval can be approved.');
        }

        $run->update(['status' => 'approved', 'approved_by' => $actor->getKey(), 'approved_at' => now()]);
        return $run->refresh();
    }

    public function recordExecution(EnterpriseOperationRun $run, array $result): EnterpriseOperationRun
    {
        $status = $run->getAttribute('status');
        if ($status !== 'approved') {
            if ($status !== 'executing') {
                throw new DomainException('An operation must be approved before execution.');
            }
        }

        $run->update(['status' => 'executed', 'result' => $result, 'executed_at' => now()]);
        return $run->refresh();
    }
}
