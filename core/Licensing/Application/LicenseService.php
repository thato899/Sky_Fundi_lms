<?php

declare(strict_types=1);

namespace Core\Licensing\Application;

use Core\Licensing\Domain\Enums\LicenseStatus;
use Core\Licensing\Domain\Services\LicenseKeyGenerator;
use Core\Licensing\Events\LicenseActivated;
use Core\Licensing\Events\LicenseCancelled;
use Core\Licensing\Events\LicenseIssued;
use Core\Licensing\Events\LicenseRenewed;
use Core\Licensing\Events\LicenseSuspended;
use Core\Licensing\Infrastructure\Models\License;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The only place a License is created or transitioned. Controllers
 * stay thin per docs/architecture/clean-architecture.md; entitlement
 * enforcement (module/user/storage limits) also lives here so every
 * caller — API, console command, a future module — gets the same
 * rules. See core/Licensing/README.md.
 */
final class LicenseService
{
    public function __construct(
        private readonly LicenseKeyGenerator $keyGenerator,
    ) {}

    public function issue(array $attributes): License
    {
        $license = License::create(array_merge($attributes, [
            'license_key' => $this->uniqueLicenseKey(),
            'status' => $attributes['status'] ?? LicenseStatus::PendingActivation,
            'created_by' => Auth::id(),
        ]));

        event(new LicenseIssued($license));

        return $license;
    }

    public function activate(License $license): License
    {
        $license->update([
            'status' => LicenseStatus::Active,
            'activation_date' => $license->activation_date ?? now()->toDateString(),
            'updated_by' => Auth::id(),
        ]);

        event(new LicenseActivated($license));

        return $license->fresh();
    }

    public function suspend(License $license, ?string $reason = null): License
    {
        $license->update(['status' => LicenseStatus::Suspended, 'updated_by' => Auth::id()]);

        event(new LicenseSuspended($license, ['reason' => $reason]));

        return $license->fresh();
    }

    public function cancel(License $license): License
    {
        $license->update(['status' => LicenseStatus::Cancelled, 'updated_by' => Auth::id()]);

        event(new LicenseCancelled($license));

        return $license->fresh();
    }

    public function renew(License $license, \DateTimeInterface $newExpiryDate): License
    {
        $before = ['expiry_date' => $license->expiry_date?->toDateString()];

        $license->update([
            'expiry_date' => $newExpiryDate,
            'status' => $license->status === LicenseStatus::Expired ? LicenseStatus::Active : $license->status,
            'updated_by' => Auth::id(),
        ]);

        event(new LicenseRenewed($license, ['before' => $before, 'after' => ['expiry_date' => $license->expiry_date->toDateString()]]));

        return $license->fresh();
    }

    /**
     * Marks any license whose expiry_date has passed as Expired.
     * Called by the scheduled `platform:validate-licenses` command
     * (see core/Scheduler/Console/ValidateLicensesCommand.php).
     */
    public function expireOverdueLicenses(): int
    {
        return DB::transaction(function (): int {
            $overdue = License::query()
                ->where('status', LicenseStatus::Active)
                ->whereNotNull('expiry_date')
                ->where('expiry_date', '<', now()->toDateString())
                ->get();

            foreach ($overdue as $license) {
                $license->update(['status' => LicenseStatus::Expired]);
                event(new \Core\Licensing\Events\LicenseExpired($license));
            }

            return $overdue->count();
        });
    }

    public function isValid(License $license): bool
    {
        return $license->status->isUsable() && ! $license->isExpired();
    }

    private function uniqueLicenseKey(): string
    {
        do {
            $key = $this->keyGenerator->generate();
        } while (License::query()->where('license_key', $key)->exists());

        return $key;
    }
}
