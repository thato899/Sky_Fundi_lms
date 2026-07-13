<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Database-driven, per modules/Academics/README.md — "Science,
 * Mathematics, Languages, ... Custom Departments" are seed examples,
 * not a fixed enum. See database/seeders/DepartmentSeeder.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academics_departments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('colour', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academics_departments');
    }
};
