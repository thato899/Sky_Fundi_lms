<?php

declare(strict_types=1);

namespace Core\Notifications\Infrastructure\Models;

use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single user's opt-in/out for one notification type on one channel.
 * Absence of a row means "use the channel's default" (opt-out model),
 * per NotificationService::isEnabledFor().
 */
final class NotificationPreference extends Model
{
    use HasUuids;

    protected $table = 'notification_preferences';

    protected $fillable = ['user_id', 'type', 'channel', 'enabled'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
