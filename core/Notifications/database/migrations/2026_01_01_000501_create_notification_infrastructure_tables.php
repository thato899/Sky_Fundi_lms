<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Standard Laravel database-notification-channel table.
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->uuidMorphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // Reusable content templates per notification type/channel —
        // see core/Notifications/README.md ("Notification Templates").
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // e.g. "auth.password_reset", "modules.module_installed"
            $table->string('key')->index();
            $table->string('channel'); // mail | database | push | sms
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['key', 'channel']);
        });

        // Per-user, per-notification-type channel opt-in/out — see
        // core/Notifications/README.md ("Notification Preferences").
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->string('channel');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'type', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('notifications');
    }
};
