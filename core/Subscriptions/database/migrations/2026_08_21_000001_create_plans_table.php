<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * See core/Subscriptions/README.md and docs/adr/010-payfast-payment-gateway.md.
 * Replaces the demo-only `config('hackathon.plans')` array as the
 * runtime source of truth for plan pricing/entitlements. `key` is the
 * same string identifier already stored in `subscriptions.plan`
 * (e.g. "starter") — kept as a plain string reference rather than a
 * new foreign-key column so existing Subscription rows and code that
 * reads `subscription->plan` are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('ZAR');
            $table->string('billing_cycle')->default('monthly');
            $table->unsignedInteger('max_learners')->nullable();
            $table->unsignedInteger('max_staff')->nullable();
            $table->unsignedInteger('ai_allowance')->nullable();
            $table->decimal('overage_rate_per_learner', 10, 2)->default(0);
            $table->string('gateway_plan_reference')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
