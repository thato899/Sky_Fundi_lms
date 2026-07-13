<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type');
            $table->string('status')->default('active');
            $table->string('registration_number')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('email')->nullable();
            $table->string('telephone')->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('timezone')->default('Africa/Johannesburg');
            $table->string('language')->default('en');
            $table->string('currency', 3)->nullable();
            $table->unsignedBigInteger('storage_quota')->default(0);
            $table->unsignedBigInteger('current_storage')->default(0);
            $table->unsignedInteger('maximum_users')->default(0);
            $table->unsignedInteger('current_users')->default(0);
            $table->string('license_key')->nullable();
            $table->string('license_type')->nullable();
            $table->date('license_expires_at')->nullable();
            $table->date('license_renews_at')->nullable();
            $table->string('support_plan')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['status', 'type']);
        });

        Schema::create('organization_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('group');
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->unique(['organization_id', 'group', 'key']);
        });

        Schema::create('organization_administrators', function (Blueprint $table): void {
            $table->uuid('organization_id');
            $table->uuid('user_id');
            $table->uuid('assigned_by')->nullable();
            $table->timestamp('assigned_at')->useCurrent();
            $table->primary(['organization_id', 'user_id']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('organization_modules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('module_name');
            $table->boolean('enabled')->default(true);
            $table->uuid('enabled_by')->nullable();
            $table->timestamps();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->unique(['organization_id', 'module_name']);
        });

        Schema::create('organization_ai_configurations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->unique();
            $table->string('provider')->nullable();
            $table->json('credentials')->nullable();
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_ai_configurations');
        Schema::dropIfExists('organization_modules');
        Schema::dropIfExists('organization_administrators');
        Schema::dropIfExists('organization_settings');
        Schema::dropIfExists('organizations');
    }
};
