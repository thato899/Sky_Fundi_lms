<?php

declare(strict_types=1);

namespace Core\Subscriptions\Infrastructure\Models;

use Core\Subscriptions\Domain\Enums\BillingCycle;
use Core\Support\Traits\HasMetadata;
use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

/**
 * A billable plan tier — pricing and entitlements a Subscription's
 * `plan` string key resolves to. See core/Subscriptions/README.md and
 * docs/adr/010-payfast-payment-gateway.md. Replaces the demo-only
 * `config('hackathon.plans')` array as the runtime source of truth;
 * `core/Billing` reads this for checkout amounts and invoice lines.
 *
 * @property string $id
 * @property string $key
 * @property string $name
 * @property string $price
 * @property string $currency
 * @property BillingCycle $billing_cycle
 * @property int|null $max_learners
 * @property int|null $max_staff
 * @property int|null $ai_allowance
 * @property string $overage_rate_per_learner
 * @property string|null $gateway_plan_reference
 * @property bool $is_active
 */
final class Plan extends Model
{
    use HasMetadata;
    use HasUuidPrimaryKey;

    protected $table = 'plans';

    protected $fillable = [
        'key', 'name', 'price', 'currency', 'billing_cycle',
        'max_learners', 'max_staff', 'ai_allowance', 'overage_rate_per_learner',
        'gateway_plan_reference', 'is_active', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'billing_cycle' => BillingCycle::class,
            'max_learners' => 'integer',
            'max_staff' => 'integer',
            'ai_allowance' => 'integer',
            'overage_rate_per_learner' => 'decimal:2',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public static function findByKey(string $key): ?self
    {
        return self::query()->where('key', $key)->first();
    }
}
