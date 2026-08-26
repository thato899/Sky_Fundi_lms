<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * See core/Billing/README.md. A payment attempt against the gateway —
 * created Pending when checkout is initiated, transitioned only by a
 * verified gateway webhook (Core\Billing\Application\PaymentWebhookService),
 * never marked paid client-side. `gateway_reference` is the gateway's
 * own transaction id, unique per gateway so a replayed webhook is a
 * no-op rather than a duplicate payment.
 *
 * `gateway_response` intentionally stores only the operational fields
 * the platform needs (amount, status, gateway payment id) — never the
 * payer's name/email or other personal data the gateway's webhook
 * payload may carry, per AGENTS.md's prohibition on logging personal
 * data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignUuid('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            $table->string('gateway');
            $table->string('gateway_reference')->nullable();
            $table->string('purpose');
            $table->string('status')->default('pending')->index();

            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('ZAR');

            $table->json('gateway_response')->nullable();
            // Our own bookkeeping for what a subscription-signup payment
            // should start once confirmed (plan key, billing cycle) — not
            // gateway-sourced data, see the class-level note above.
            $table->json('metadata')->nullable();
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();

            $table->unique(['gateway', 'gateway_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
