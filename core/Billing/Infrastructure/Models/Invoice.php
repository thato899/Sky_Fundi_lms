<?php

declare(strict_types=1);

namespace Core\Billing\Infrastructure\Models;

use Core\Billing\Domain\Enums\InvoiceStatus;
use Core\Subscriptions\Infrastructure\Models\Subscription;
use Core\Support\Traits\HasMetadata;
use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Organizations\Infrastructure\Models\Organization;

/**
 * A billing-cycle invoice generated from real Attendance/enrolment
 * usage against an organization's Subscription — see
 * core/Billing/README.md and docs/adr/010-payfast-payment-gateway.md.
 * Never hand-entered; built by Application\InvoiceService.
 *
 * @property string $id
 * @property string $organization_id
 * @property string|null $subscription_id
 * @property InvoiceStatus $status
 * @property Carbon $billing_period_start
 * @property Carbon $billing_period_end
 * @property string $currency
 * @property string $subtotal
 * @property string $total
 * @property Carbon|null $due_date
 * @property Carbon|null $issued_at
 * @property Carbon|null $paid_at
 * @property string|null $external_reference
 * @property Subscription|null $subscription
 * @property Collection<int, InvoiceLineItem> $lineItems
 */
final class Invoice extends Model
{
    use HasMetadata;
    use HasUuidPrimaryKey;
    use SoftDeletes;

    protected $table = 'invoices';

    protected $fillable = [
        'organization_id', 'subscription_id',
        'billing_period_start', 'billing_period_end', 'status',
        'currency', 'subtotal', 'total',
        'due_date', 'issued_at', 'paid_at',
        'external_reference', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'due_date' => 'date',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'metadata' => 'array',
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

    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isPayable(): bool
    {
        return in_array($this->status, [InvoiceStatus::Issued, InvoiceStatus::Overdue], true);
    }
}
