<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every configurable platform-level value lives here — "no hardcoded
 * configuration" per core/Settings/README.md. Grouped so the API and
 * admin UI can present related settings together (branding, email,
 * security, ai, storage...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('group')->index();
            $table->json('value')->nullable();
            // Settings that hold secrets (API keys) are encrypted at rest
            // via the SettingsService, never stored in plaintext — see
            // docs/security/policies.md#encryption.
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
