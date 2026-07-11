<?php

declare(strict_types=1);

namespace Core\Notifications\Application;

use Core\Notifications\Infrastructure\Models\NotificationPreference;
use Core\Notifications\Infrastructure\Notifications\CoreNotification;
use Core\Users\Infrastructure\Models\User;

/**
 * The single dispatch path for platform notifications. Core services
 * and future modules call `send()` rather than constructing Notification
 * classes directly, so channel resolution (database, mail, and future
 * push/SMS — see core/Notifications/README.md) and per-user preferences
 * are applied consistently everywhere.
 */
final class NotificationService
{
    private const DEFAULT_CHANNELS = ['database', 'mail'];

    public function send(User $user, string $type, array $data = [], array $channels = self::DEFAULT_CHANNELS): void
    {
        $enabledChannels = array_values(array_filter(
            $channels,
            fn (string $channel) => $this->isEnabledFor($user, $type, $channel),
        ));

        if ($enabledChannels === []) {
            return;
        }

        $user->notify(new CoreNotification($type, $data, $enabledChannels));
    }

    public function isEnabledFor(User $user, string $type, string $channel): bool
    {
        $preference = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('channel', $channel)
            ->first();

        // Opt-out model: no explicit preference row means the channel's
        // default (enabled) applies.
        return $preference?->enabled ?? true;
    }

    public function setPreference(User $user, string $type, string $channel, bool $enabled): NotificationPreference
    {
        return NotificationPreference::query()->updateOrCreate(
            ['user_id' => $user->id, 'type' => $type, 'channel' => $channel],
            ['enabled' => $enabled],
        );
    }

    public function preferencesFor(User $user): array
    {
        return NotificationPreference::query()
            ->where('user_id', $user->id)
            ->get(['type', 'channel', 'enabled'])
            ->toArray();
    }
}
