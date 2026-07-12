<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * See core/Deployment/README.md. `subject_type`/`subject_id` mirror
 * the nullable-polymorphic pattern used by Core\Licensing — a null
 * subject is the platform's own deployment profile; a future
 * Organization/tenant can attach here once that model exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployment_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('subject');

            $table->string('strategy');
            $table->json('database_config')->nullable();
            $table->json('storage_config')->nullable();
            $table->json('branding_config')->nullable();
            $table->json('environment_config')->nullable();
            $table->string('ai_provider')->nullable();
            $table->json('modules')->nullable();
            $table->foreignUuid('administrator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_profiles');
    }
};
