<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academics_grades', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->unsignedSmallInteger('order');
            $table->foreignUuid('curriculum_id')->nullable()->constrained('academics_curricula')->nullOnDelete();
            $table->foreignUuid('academic_year_id')->nullable()->constrained('academics_academic_years')->nullOnDelete();
            $table->string('status')->default('active');
            $table->softDeletes();
            $table->timestamps();

            $table->index('order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academics_grades');
    }
};
