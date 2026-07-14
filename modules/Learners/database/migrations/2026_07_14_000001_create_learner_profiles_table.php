<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('organization_membership_id')->nullable()->constrained('organization_memberships')->nullOnDelete();

            $table->string('learner_number');
            $table->string('admission_number')->nullable();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('preferred_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('profile_photo_path')->nullable();

            $table->foreignUuid('current_academic_year_id')->nullable()->constrained('academics_academic_years')->nullOnDelete();
            $table->foreignUuid('current_grade_id')->nullable()->constrained('academics_grades')->nullOnDelete();
            $table->foreignUuid('current_class_id')->nullable()->constrained('academics_classes')->nullOnDelete();
            $table->foreignUuid('curriculum_id')->nullable()->constrained('academics_curricula')->nullOnDelete();
            $table->date('admission_date')->nullable();
            $table->date('expected_completion_date')->nullable();
            $table->string('previous_institution')->nullable();
            $table->string('language_of_instruction')->nullable();
            $table->string('home_language')->nullable();
            $table->string('learning_mode')->nullable();

            $table->string('learner_email')->nullable();
            $table->string('learner_phone')->nullable();
            $table->text('residential_address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code')->nullable();

            $table->string('learner_status')->default('pending');
            $table->string('academic_status')->nullable();
            $table->string('onboarding_status')->default('pending');
            $table->boolean('portal_access_enabled')->default(false);
            $table->json('metadata')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'learner_number']);
            $table->unique(['organization_id', 'admission_number']);
            $table->index('learner_status');
            $table->index('current_grade_id');
            $table->index('current_class_id');
            $table->index('portal_access_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_profiles');
    }
};
