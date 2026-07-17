<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table): void {
            $table->unsignedInteger('max_learners')->nullable()->after('max_users');
            $table->index(['licensee_type', 'licensee_id', 'status'], 'licenses_licensee_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table): void {
            $table->dropIndex('licenses_licensee_status_index');
            $table->dropColumn('max_learners');
        });
    }
};
