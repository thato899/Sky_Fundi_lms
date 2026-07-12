<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * See core/FeatureFlags/README.md. `scope_type`/`scope_id` on the
 * overrides table are plain strings, not a polymorphic relation —
 * "organization" doesn't have a model yet (see
 * docs/architecture/multi-tenancy.md), and "module" identifies a
 * Core\Modules\Infrastructure\Models\ModuleRegistration by its `name`,
 * not a foreign key, since modules can be removed and reinstalled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_enabled_globally')->default(false);
            $table->timestamps();
        });

        Schema::create('feature_flag_overrides', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('feature_flag_id')->constrained('feature_flags')->cascadeOnDelete();
            $table->string('scope_type'); // platform | organization | user | module
            $table->string('scope_id')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['feature_flag_id', 'scope_type', 'scope_id'], 'feature_flag_overrides_unique_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flag_overrides');
        Schema::dropIfExists('feature_flags');
    }
};
