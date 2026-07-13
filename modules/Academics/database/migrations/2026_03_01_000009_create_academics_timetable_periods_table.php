<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reusable building blocks a future scheduling/timetable-
 * generation module would assign classes/subjects/teachers onto — no
 * such assignment exists yet, per modules/Academics/README.md#timetable-foundation
 * ("No timetable generation yet").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academics_timetable_periods', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_break')->default(false);
            $table->unsignedSmallInteger('order');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['day_of_week', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academics_timetable_periods');
    }
};
