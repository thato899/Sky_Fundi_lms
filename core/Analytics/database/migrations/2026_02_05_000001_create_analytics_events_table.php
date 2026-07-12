<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A lightweight, append-only event stream — infrastructure for future
 * aggregation/dashboards, not itself a reporting table. See
 * core/Analytics/README.md. Deliberately separate from audit_logs:
 * audit logs are a security/compliance trail of discrete sensitive
 * actions (see docs/security/README.md); analytics_events is a
 * high-volume, low-detail counter stream meant to be rolled up and
 * pruned, not kept forever.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('metric')->index();
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->decimal('value', 20, 4)->default(1);
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at')->useCurrent()->index();

            $table->index(['metric', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
