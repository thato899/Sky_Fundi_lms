<?php

declare(strict_types=1);

namespace Core\Licensing\Infrastructure\Models;

use Core\Licensing\Domain\Enums\LicenseStatus;
use Core\Licensing\Domain\Enums\LicenseTier;
use Core\Support\Traits\HasMetadata;
use Core\Support\Traits\HasUuidPrimaryKey;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An entitlement contract: what a licensee is allowed (max users,
 * max storage, which modules, which AI provider, support level) and
 * for how long. See core/Licensing/README.md. Never enforced
 * ad hoc — always through Core\Licensing\Application\LicenseService.
 */
final class License extends Model
{
    use HasMetadata;
    use HasUuidPrimaryKey;
    use SoftDeletes;

    protected $table = 'licenses';

    protected $fillable = [
        'licensee_type', 'licensee_id', 'license_key', 'tier', 'status',
        'activation_date', 'expiry_date', 'renewal_date',
        'max_users', 'max_storage_mb', 'enabled_modules', 'ai_provider',
        'support_level', 'metadata', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'tier' => LicenseTier::class,
            'status' => LicenseStatus::class,
            'activation_date' => 'date',
            'expiry_date' => 'date',
            'renewal_date' => 'date',
            'enabled_modules' => 'array',
            'metadata' => 'array',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    public function allowsModule(string $moduleName): bool
    {
        return $this->enabled_modules === null || in_array($moduleName, $this->enabled_modules, true);
    }
}
