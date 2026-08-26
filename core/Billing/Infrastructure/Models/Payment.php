<?php

declare(strict_types=1);

namespace Core\Billing\Infrastructure\Models;

use Core\Billing\Domain\Enums\PaymentPurpose;
use Core\Billing\Domain\Enums\PaymentStatus;
use Core\Subscriptions\Infrastructure\Models\Subscription;
use Core\Support\Traits\HasMetadata;
use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Organizations\Infrastructure\Models\Organization;

/**
 * A single payment attempt against a gateway. Created Pending at
 * checkout, transitioned only by a verified webhook — see
 * core/Billing/README.md and Application\PaymentWebhookService.
 *
 * @property string $id
 * @property string $organization_id
 * @property string|null $subscription_id
 * @property string|null $invoice_id
 * @property string $gateway
 * @property string|null $gateway_reference
 * @property PaymentPurpose $purpose
 * @property PaymentStatus $status
 * @property string $amount
 * @property string $currency
 * @property array|null $gateway_response
 * @property Carbon|null $confirmed_at
 * @property Carbon $created_at
 */
final class Payment extends Model
{
    use HasMetadata;
    use HasUuidPrimaryKey;

    protected $table = 'payments';

    protected $fillable = [
        'organization_id', 'subscription_id', 'invoice_id',
        'gateway', 'gateway_reference', 'purpose', 'status',
        'amount', 'currency', 'gateway_response', 'metadata', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => PaymentPurpose::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'gateway_response' => 'array',
            'metadata' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
