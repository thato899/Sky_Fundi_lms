<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learner_enrolments', function (Blueprint $table): void {
            $table->foreignUuid('enterprise_operation_run_id')->nullable()->after('actor_id')
                ->constrained('enterprise_operation_runs')->nullOnDelete();
            $table->index(['enterprise_operation_run_id', 'learner_profile_id'], 'enrolment_operation_learner_idx');
        });
    }

    public function down(): void
    {
        Schema::table('learner_enrolments', function (Blueprint $table): void {
            $table->dropForeign(['enterprise_operation_run_id']);
            $table->dropIndex('enrolment_operation_learner_idx');
            $table->dropColumn('enterprise_operation_run_id');
        });
    }
};
