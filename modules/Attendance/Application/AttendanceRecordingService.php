<?php

declare(strict_types=1);

namespace Modules\Attendance\Application;

use Carbon\CarbonImmutable;
use Core\AuditLogs\Application\AuditLogService;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Domain\Enums\AttendanceSessionStatus;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Infrastructure\Models\AttendanceEntry;
use Modules\Attendance\Infrastructure\Models\AttendanceSession;

final class AttendanceRecordingService
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function record(AttendanceSession $session, User $actor, array $rows): AttendanceSession
    {
        return DB::transaction(function () use ($session, $actor, $rows): AttendanceSession {
            /** @var AttendanceSession $locked */
            $locked = AttendanceSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            if (! in_array($locked->getAttribute('status'), [AttendanceSessionStatus::Draft, AttendanceSessionStatus::Open], true)) {
                throw new DomainException('Finalized or cancelled attendance cannot be changed.');
            }
            /** @var Collection<string, AttendanceEntry> $entries */
            $entries = $locked->entries()->with('learner')->lockForUpdate()->get()->keyBy('uuid');
            if (count($rows) !== $entries->count()) {
                throw new DomainException('The complete eligible register must be submitted atomically.');
            }
            foreach ($rows as $row) {
                $entry = $entries->get($row['entry_uuid'] ?? '');
                if (! $entry instanceof AttendanceEntry || $entry->getAttribute('organization_id') !== $locked->getAttribute('organization_id')) {
                    throw new DomainException('The register contains an invalid learner entry.');
                }
                $status = AttendanceStatus::tryFrom((string) ($row['status'] ?? ''));
                if ($status === null) {
                    throw new DomainException('The register contains an invalid attendance status.');
                }
                $arrival = $row['arrival_time'] ?? null;
                $minutesLate = null;
                if ($status === AttendanceStatus::Late && $arrival && $locked->getAttribute('start_time')) {
                    $start = CarbonImmutable::parse($locked->getAttribute('session_date')->toDateString().' '.$locked->getAttribute('start_time'));
                    $arrived = CarbonImmutable::parse($locked->getAttribute('session_date')->toDateString().' '.$arrival);
                    $minutesLate = max(0, $start->diffInMinutes($arrived, false));
                }
                $entry->update(['status' => $status, 'arrival_time' => $arrival, 'departure_time' => $row['departure_time'] ?? null, 'minutes_late' => $minutesLate, 'reason' => $row['reason'] ?? null, 'note' => $row['note'] ?? null, 'recorded_by' => $entry->getAttribute('recorded_by') ?? $actor->getKey(), 'updated_by' => $actor->getKey()]);
            }
            $locked->update(['status' => AttendanceSessionStatus::Open, 'updated_by' => $actor->getKey()]);
            $this->audit->record('attendance.register_recorded', $locked, after: ['organization_id' => $locked->getAttribute('organization_id'), 'entry_count' => count($rows)]);

            return $locked->refresh()->load('entries.learner');
        }, 3);
    }
}
