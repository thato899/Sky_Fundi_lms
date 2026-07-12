<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * See core/Licensing/README.md. `licensee_type`/`licensee_id` are a
 * nullable polymorphic pair so a license can attach to a future
 * licensable entity (e.g. an Organization once
 * docs/architecture/multi-tenancy.md's tenant model is implemented)
 * without this table depending on that not-yet-existing table today.
 * A null licensee means a platform-wide license.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('licensee');

            $table->string('license_key')->unique();
            $table->string('tier');
            $table->string('status')->index();

            $table->date('activation_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('renewal_date')->nullable();

            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedBigInteger('max_storage_mb')->nullable();
            $table->json('enabled_modules')->nullable();
            $table->string('ai_provider')->nullable();
            $table->string('support_level')->nullable();

            $table->json('metadata')->nullable();

            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
