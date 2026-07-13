<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academics_subjects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignUuid('curriculum_id')->nullable()->constrained('academics_curricula')->nullOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained('academics_departments')->nullOnDelete();
            $table->string('colour', 7)->nullable();
            // Reserved for a future per-subject AI tutor configuration
            // (default provider/model/prompt template) resolved through
            // Core\AIGateway — nothing reads or writes this column yet.
            $table->json('ai_configuration')->nullable();
            $table->string('status')->default('active');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academics_subjects');
    }
};
