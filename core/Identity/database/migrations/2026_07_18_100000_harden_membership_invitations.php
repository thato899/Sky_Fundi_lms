<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_memberships', function (Blueprint $table): void {
            $table->uuid('user_id')->nullable()->change();
            $table->string('invited_email')->nullable()->after('invited_by');
            $table->timestamp('invitation_sent_at')->nullable()->after('invitation_expires_at');
            $table->timestamp('revoked_at')->nullable()->after('invitation_sent_at');
            $table->unsignedSmallInteger('resend_count')->default(0)->after('revoked_at');
            $table->index(['organization_id', 'invited_email', 'status'], 'membership_invitation_email_status');
        });
    }

    public function down(): void
    {
        if (DB::table('organization_memberships')->whereNull('user_id')->exists()) {
            throw new RuntimeException('Cannot roll back guardian invitations while email-first memberships are pending; revoke or accept them first.');
        }

        Schema::table('organization_memberships', function (Blueprint $table): void {
            $table->dropIndex('membership_invitation_email_status');
            $table->dropColumn(['invited_email', 'invitation_sent_at', 'revoked_at', 'resend_count']);
        });

        Schema::table('organization_memberships', function (Blueprint $table): void {
            $table->uuid('user_id')->nullable(false)->change();
        });
    }
};
