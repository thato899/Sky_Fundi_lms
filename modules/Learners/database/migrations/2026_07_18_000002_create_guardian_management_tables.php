<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('organization_membership_id')->nullable()->constrained('organization_memberships')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('preferred_communication_channel')->default('email');
            $table->text('address')->nullable();
            $table->string('status')->default('active');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'last_name', 'first_name']);
            $table->unique(['organization_id', 'organization_membership_id'], 'guardian_membership_unique');
        });

        Schema::create('learner_guardian_relationships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('learner_profile_id')->constrained('learner_profiles')->cascadeOnDelete();
            $table->foreignUuid('guardian_profile_id')->constrained('guardian_profiles')->cascadeOnDelete();
            $table->string('relationship_type');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_emergency_contact')->default(false);
            $table->boolean('is_authorized_pickup')->default(false);
            $table->boolean('receives_academic_communication')->default(true);
            $table->boolean('receives_financial_communication')->default(false);
            $table->string('status')->default('active');
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedTinyInteger('active_primary_guardian')->nullable()->storedAs("case when `status` = 'active' and `is_primary` = 1 and `deleted_at` is null then 1 else null end");
            $table->unique(['learner_profile_id', 'guardian_profile_id'], 'learner_guardian_unique');
            $table->unique(['learner_profile_id', 'active_primary_guardian'], 'learner_one_active_primary_unique');
            $table->index(['organization_id', 'learner_profile_id', 'status'], 'learner_guardian_learner_status_index');
            $table->index(['organization_id', 'guardian_profile_id', 'status'], 'learner_guardian_guardian_status_index');
        });

        Schema::create('learner_consents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('learner_profile_id')->constrained('learner_profiles')->cascadeOnDelete();
            $table->foreignUuid('guardian_profile_id')->nullable()->constrained('guardian_profiles')->nullOnDelete();
            $table->string('consent_type');
            $table->string('status');
            $table->date('recorded_date');
            $table->date('expiry_date')->nullable();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'learner_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_consents');
        Schema::dropIfExists('learner_guardian_relationships');
        Schema::dropIfExists('guardian_profiles');
    }
};
