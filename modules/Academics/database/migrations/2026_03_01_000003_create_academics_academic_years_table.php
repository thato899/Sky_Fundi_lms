<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * See modules/Academics/README.md#academic-year. `is_current` is a
 * denormalised convenience flag kept in sync by
 * Application\AcademicYearService::setCurrent(), which guarantees at
 * most one row has it set — a partial unique index isn't used here
 * because it would need to be MySQL 8 filtered-index syntax that
 * SQLite (the test database, see phpunit.xml) doesn't support the
 * same way; the invariant is enforced at the application layer
 * instead, consistent with how Core\Modules\ModuleRegistration and
 * other Core services already handle single-row invariants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academics_academic_years', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('upcoming');
            $table->boolean('is_current')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
            $table->index('is_current');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academics_academic_years');
    }
};
