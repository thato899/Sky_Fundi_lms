<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * See modules/Leaderboards/README.md.
 *
 * `leaderboards` + `leaderboard_entries` hold BOTH academic and sports
 * standings — academic entries are computed from already-approved
 * ReportCard/ReportCardSubjectResult snapshots (never raw marks), sports
 * entries from summed `sports_records` points. `leaderboard_entries`
 * deliberately has no column for anything beyond rank and a single
 * aggregate value — there is nowhere for a subject-by-subject mark to
 * leak through even by accident. `visibility` gates whether anyone
 * other than a learner's own entry (and Leaderboards\Application\
 * LeaderboardService's manage-permission holders) can see the full
 * ranked list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaderboards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->string('type', 16); // academic | sports
            $table->string('name');
            $table->foreignUuid('subject_id')->nullable()->constrained('academics_subjects')->nullOnDelete();
            $table->foreignUuid('grade_id')->nullable()->constrained('academics_grades')->nullOnDelete();
            $table->foreignUuid('class_id')->nullable()->constrained('academics_classes')->nullOnDelete();
            // Academic leaderboards read an existing reporting period's
            // approved report cards; sports leaderboards sum points over
            // a plain date range instead, since sports has no reporting
            // period concept.
            $table->foreignUuid('reporting_period_id')->nullable()->constrained('reporting_periods')->nullOnDelete();
            $table->date('period_start_date')->nullable();
            $table->date('period_end_date')->nullable();

            $table->string('visibility', 16)->default('private'); // private | published
            $table->timestamp('generated_at')->nullable();
            $table->foreignUuid('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignUuid('published_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['organization_id', 'type', 'visibility']);
        });

        Schema::create('leaderboard_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('leaderboard_id')->constrained('leaderboards')->cascadeOnDelete();
            $table->foreignUuid('learner_profile_id')->constrained('learner_profiles')->cascadeOnDelete();

            $table->unsignedInteger('rank');
            // The one number a leaderboard is allowed to show: an overall
            // or per-subject average percentage for academic, a summed
            // points total for sports. Never a raw mark.
            $table->decimal('value', 6, 2);

            $table->timestamps();

            $table->unique(['leaderboard_id', 'learner_profile_id']);
            $table->index(['organization_id', 'leaderboard_id', 'rank']);
        });

        Schema::create('sports_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('learner_profile_id')->constrained('learner_profiles')->cascadeOnDelete();

            $table->string('activity');
            $table->decimal('points', 6, 2);
            $table->date('event_date');
            $table->text('notes')->nullable();
            $table->foreignUuid('recorded_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();

            // Explicit short name: MySQL's default auto-generated name for
            // this column combination exceeds its 64-character identifier
            // limit.
            $table->index(['organization_id', 'learner_profile_id', 'event_date'], 'sports_records_org_learner_date_index');
        });

        Schema::create('sportsperson_of_the_week', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('learner_profile_id')->constrained('learner_profiles')->cascadeOnDelete();

            $table->date('week_start_date');
            $table->text('citation');
            $table->boolean('is_published')->default(false);
            $table->foreignUuid('posted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            // Explicit short name: the default auto-generated name for this
            // table/column combination sits right at MySQL's 64-character
            // identifier limit — named explicitly rather than relying on
            // staying just under it.
            $table->unique(['organization_id', 'week_start_date'], 'sportsperson_org_week_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sportsperson_of_the_week');
        Schema::dropIfExists('sports_records');
        Schema::dropIfExists('leaderboard_entries');
        Schema::dropIfExists('leaderboards');
    }
};
