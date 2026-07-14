<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_number_sequences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('academic_year')->default('');
            $table->string('prefix', 32)->default('LRN');
            $table->unsignedTinyInteger('padding')->default(6);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['organization_id', 'academic_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_number_sequences');
    }
};
