<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academics_academic_terms', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_year_id')->constrained('academics_academic_years')->cascadeOnDelete();
            $table->unsignedTinyInteger('term_number');
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('upcoming');
            $table->boolean('is_current')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['academic_year_id', 'term_number']);
            $table->index('is_current');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academics_academic_terms');
    }
};
