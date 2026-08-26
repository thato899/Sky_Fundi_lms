<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * See core/Billing/README.md. One invoice per organization per billing
 * cycle, generated from the organization's active Subscription plus
 * real Attendance/enrolment usage for that period — never hand-entered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();

            $table->date('billing_period_start');
            $table->date('billing_period_end');
            $table->string('status')->default('draft')->index();

            $table->string('currency', 3)->default('ZAR');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->date('due_date')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->string('external_reference')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // One generated invoice per subscription per billing period —
            // the scheduled generator must not double-bill a cycle.
            $table->unique(['subscription_id', 'billing_period_start', 'billing_period_end'], 'invoices_subscription_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
