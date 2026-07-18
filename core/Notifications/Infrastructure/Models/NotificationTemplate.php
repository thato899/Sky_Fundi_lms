<?php

declare(strict_types=1);

namespace Core\Notifications\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A reusable, editable message body per notification type + channel,
 * so copy changes don't require a code deploy. See
 * core/Notifications/README.md.
 *
 * @property string|null $subject
 * @property string $body
 */
final class NotificationTemplate extends Model
{
    use HasUuids;

    protected $table = 'notification_templates';

    protected $fillable = ['key', 'channel', 'subject', 'body', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Renders {{placeholder}} tokens in the template body against the
     * given data. Deliberately minimal — modules needing richer
     * templating can layer their own renderer on top of the raw body.
     */
    public function render(array $data = []): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
            fn (array $matches) => (string) ($data[$matches[1]] ?? ''),
            $this->body,
        );
    }
}
