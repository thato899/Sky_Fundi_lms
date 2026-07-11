<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Nullable — some actions (e.g. a failed login for an unknown
            // email) have no resolvable actor. See docs/security/README.md.
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_email')->nullable();

            // <module>.<entity>.<action>, e.g. "user.suspended",
            // "role.permissions_synced" — see docs/naming-conventions.md.
            $table->string('action')->index();

            // Polymorphic target the action was performed against.
            $table->string('target_type')->nullable();
            $table->string('target_id')->nullable();

            $table->json('before')->nullable();
            $table->json('after')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Audit logs are immutable — no updated_at, no soft deletes.
            // See docs/security/README.md#audit-logs.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['target_type', 'target_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
