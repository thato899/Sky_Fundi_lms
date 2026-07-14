<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_memberships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('organization_id');
            $table->uuid('role_id')->nullable();
            $table->string('status')->default('invited');
            $table->timestamp('joined_at')->nullable();
            $table->uuid('invited_by')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_default')->default(false);
            $table->string('invitation_token', 64)->nullable()->unique();
            $table->timestamp('invitation_expires_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['user_id', 'organization_id']);
            $table->index(['organization_id', 'status']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
        });
        Schema::table('roles', function (Blueprint $table): void {
            $table->uuid('organization_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('roles', fn (Blueprint $table) => $table->dropColumn('organization_id'));
        Schema::dropIfExists('organization_memberships');
    }
};
