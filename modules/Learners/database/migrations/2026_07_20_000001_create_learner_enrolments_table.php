<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_enrolments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('learner_profile_id')->constrained('learner_profiles')->cascadeOnDelete();
            $table->foreignUuid('academic_year_id')->nullable()->constrained('academics_academic_years')->nullOnDelete();
            $table->foreignUuid('grade_id')->nullable()->constrained('academics_grades')->nullOnDelete();
            $table->foreignUuid('class_id')->nullable()->constrained('academics_classes')->nullOnDelete();
            $table->foreignUuid('curriculum_id')->nullable()->constrained('academics_curricula')->nullOnDelete();
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unsignedTinyInteger('is_open')->nullable()->storedAs('case when `ended_on` is null then 1 else null end');
            $table->unique(['learner_profile_id', 'is_open'], 'learner_one_open_enrolment_unique');
            $table->index(['organization_id', 'learner_profile_id', 'started_on'], 'learner_enrolment_lookup');
            $table->index(['organization_id', 'class_id'], 'learner_enrolment_class_lookup');
        });

        // Backfill: learners placed before enrolment tracking receive their
        // current placement as the initial open enrolment row.
        $now = now();
        DB::table('learner_profiles')
            ->select(['id', 'organization_id', 'current_academic_year_id', 'current_grade_id', 'current_class_id', 'curriculum_id', 'admission_date', 'created_at'])
            ->whereNull('deleted_at')
            ->where(function ($query): void {
                $query->whereNotNull('current_academic_year_id')
                    ->orWhereNotNull('current_grade_id')
                    ->orWhereNotNull('current_class_id')
                    ->orWhereNotNull('curriculum_id');
            })
            ->orderBy('id')
            ->chunkById(500, function ($learners) use ($now): void {
                $rows = [];
                foreach ($learners as $learner) {
                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'organization_id' => $learner->organization_id,
                        'learner_profile_id' => $learner->id,
                        'academic_year_id' => $learner->current_academic_year_id,
                        'grade_id' => $learner->current_grade_id,
                        'class_id' => $learner->current_class_id,
                        'curriculum_id' => $learner->curriculum_id,
                        'started_on' => $learner->admission_date
                            ?? ($learner->created_at !== null ? substr((string) $learner->created_at, 0, 10) : $now->toDateString()),
                        'ended_on' => null,
                        'actor_id' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                DB::table('learner_enrolments')->insert($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_enrolments');
    }
};
