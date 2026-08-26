<?php

declare(strict_types=1);

namespace Core\Billing\Infrastructure\Models;

use Core\Billing\Domain\Enums\InvoiceLineType;
use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One priced line on an Invoice. See core/Billing/README.md.
 *
 * @property string $id
 * @property string $invoice_id
 * @property InvoiceLineType $line_type
 * @property string $description
 * @property int $quantity
 * @property string $unit_price
 * @property string $amount
 * @property array|null $metadata
 */
final class InvoiceLineItem extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'invoice_line_items';

    protected $fillable = ['invoice_id', 'line_type', 'description', 'quantity', 'unit_price', 'amount', 'metadata'];

    protected function casts(): array
    {
        return [
            'line_type' => InvoiceLineType::class,
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
