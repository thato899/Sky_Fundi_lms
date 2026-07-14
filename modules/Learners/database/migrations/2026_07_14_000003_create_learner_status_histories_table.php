<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_status_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('learner_profile_id')->constrained('learner_profiles')->cascadeOnDelete();
            $table->string('previous_status');
            $table->string('new_status');
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('changed_at');

            $table->index(['organization_id', 'learner_profile_id', 'changed_at'], 'learner_status_history_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_status_histories');
    }
};
