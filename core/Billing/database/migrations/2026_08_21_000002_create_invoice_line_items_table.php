<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * See core/Billing/README.md. Each row is one priced line on an
 * Invoice — a flat plan-fee line and, where usage exceeds the plan's
 * cap, an attendance-derived overage line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_line_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();

            $table->string('line_type');
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_line_items');
    }
};
