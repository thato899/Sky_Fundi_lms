<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table): void {
            $table->string('title')->nullable()->after('employee_number');
            $table->string('first_name')->nullable()->after('title');
            $table->string('last_name')->nullable()->after('first_name');
            $table->text('notes')->nullable()->after('onboarding_status');
            $table->boolean('portal_access_enabled')->default(false)->after('notes');
            $table->index(['organization_id', 'last_name', 'first_name'], 'staff_organization_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table): void {
            $table->dropIndex('staff_organization_name_index');
            $table->dropColumn(['title', 'first_name', 'last_name', 'notes', 'portal_access_enabled']);
        });
    }
};
