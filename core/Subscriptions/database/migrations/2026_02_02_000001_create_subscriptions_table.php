<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * See core/Subscriptions/README.md. Subscription *history* is
 * deliberately not a separate table here — it's already covered by
 * Core\AuditLogs (every transition below is Auditable, see
 * Core\AuditLogs\Listeners\AuditableEventSubscriber), which avoids
 * duplicating a generic "action trail" concept the platform already
 * has. `external_reference`/`metadata` are reserved for a future
 * payment gateway integration (invoices, gateway subscription id) —
 * not implemented here per the brief ("no payment gateway
 * integration yet").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('subscriber');
            $table->foreignUuid('license_id')->nullable()->constrained('licenses')->nullOnDelete();

            $table->string('plan');
            $table->string('billing_cycle');
            $table->string('status')->index();

            $table->date('started_at');
            $table->date('renewal_date')->nullable();
            $table->date('grace_period_ends_at')->nullable();

            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('current_users')->default(0);
            $table->unsignedBigInteger('max_storage_mb')->nullable();
            $table->unsignedBigInteger('current_storage_mb')->default(0);

            $table->json('ai_usage')->nullable();
            $table->json('module_access')->nullable();

            $table->string('external_reference')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
