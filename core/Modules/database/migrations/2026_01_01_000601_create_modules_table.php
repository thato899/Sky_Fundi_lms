<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Module Manager's registry — see
 * docs/architecture/module-system.md#module-manifest-modulejson. One
 * row per module known to this platform installation, independent of
 * whether it's currently enabled for any given tenant (see
 * enabled_for_tenants, a JSON list of tenant identifiers, since a
 * module's enablement is per-tenant while its installation is
 * platform-wide).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->unique(); // matches module.json "name"
            $table->string('display_name');
            $table->string('version');
            $table->text('description')->nullable();
            $table->string('author')->nullable();
            $table->string('status')->default('installed')->index();
            $table->json('dependencies')->nullable(); // coreDependencies + moduleDependencies
            $table->json('tenant_types')->nullable();
            $table->json('enabled_for_tenants')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
