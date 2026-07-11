<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // <module>.<resource>.<action> — see docs/naming-conventions.md#permissions
            $table->string('name')->unique();
            $table->string('module')->index();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            // System roles (Super Admin, Platform Administrator, Support,
            // Developer) cannot be deleted through the API — see RoleSeeder.
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->foreignUuid('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        // Polymorphic assignment so future modules can attach roles to
        // more than just users if ever needed, without a schema change.
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->uuidMorphs('model');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->foreignUuid('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->uuidMorphs('model');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
