<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * See core/Security/README.md. Complements — never duplicates —
 * Core\Users' existing account-lockout columns
 * (failed_login_attempts/locked_at) and Core\Auth's Sanctum session
 * table: this migration adds device trust and IP allow/deny lists,
 * which are new concerns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trusted_devices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('fingerprint'); // hash of IP + user agent at time of trust
            $table->string('device_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('trusted_at');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint']);
        });

        Schema::create('ip_restrictions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // "platform" (scope_id null) or "organization" (scope_id is
            // that organization's identifier as a plain string — see
            // docs/architecture/multi-tenancy.md; no FK, no Organization
            // model exists yet).
            $table->string('scope_type')->default('platform');
            $table->string('scope_id')->nullable();
            $table->enum('type', ['allow', 'deny']);
            $table->string('ip_cidr'); // single IP or CIDR range, e.g. 10.0.0.0/8
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['scope_type', 'scope_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_restrictions');
        Schema::dropIfExists('trusted_devices');
    }
};
