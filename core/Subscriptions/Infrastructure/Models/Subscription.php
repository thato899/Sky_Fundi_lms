<?php

declare(strict_types=1);

namespace Core\Subscriptions\Infrastructure\Models;

use Core\Licensing\Infrastructure\Models\License;
use Core\Subscriptions\Domain\Enums\BillingCycle;
use Core\Subscriptions\Domain\Enums\SubscriptionStatus;
use Core\Support\Traits\HasMetadata;
use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A billing-cycle-scoped subscription against an (optional) License —
 * see core/Subscriptions/README.md for the distinction between the
 * two: a License is the entitlement contract, a Subscription is the
 * billing period + live usage tracking against it.
 */
final class Subscription extends Model
{
    use HasMetadata;
    use HasUuidPrimaryKey;
    use SoftDeletes;

    protected $table = 'subscriptions';

    protected $fillable = [
        'subscriber_type', 'subscriber_id', 'license_id', 'plan', 'billing_cycle', 'status',
        'started_at', 'renewal_date', 'grace_period_ends_at',
        'max_users', 'current_users', 'max_storage_mb', 'current_storage_mb',
        'ai_usage', 'module_access', 'external_reference', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'billing_cycle' => BillingCycle::class,
            'status' => SubscriptionStatus::class,
            'started_at' => 'date',
            'renewal_date' => 'date',
            'grace_period_ends_at' => 'date',
            'ai_usage' => 'array',
            'module_access' => 'array',
            'metadata' => 'array',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function isOverUserLimit(): bool
    {
        return $this->max_users !== null && $this->current_users > $this->max_users;
    }

    public function isOverStorageLimit(): bool
    {
        return $this->max_storage_mb !== null && $this->current_storage_mb > $this->max_storage_mb;
    }
}
