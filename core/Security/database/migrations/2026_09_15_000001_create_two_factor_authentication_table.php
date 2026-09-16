<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('two_factor_authentication', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->text('secret');
            $table->json('recovery_codes');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_authentication');
    }
};
