<?php

declare(strict_types=1);

namespace Core\Notifications\Infrastructure\Notifications;

use Core\Notifications\Infrastructure\Models\NotificationTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A generic, template-driven notification. Core services and modules
 * dispatch platform notifications through
 * Core\Notifications\Application\NotificationService rather than
 * writing bespoke Notification classes per message, so channel
 * selection, templates, and preferences are handled consistently. See
 * core/Notifications/README.md.
 */
final class CoreNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $type,
        private readonly array $data,
        private readonly array $channels,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $template = NotificationTemplate::query()
            ->where('key', $this->type)
            ->where('channel', 'mail')
            ->where('is_active', true)
            ->first();

        $mail = new MailMessage();

        if ($template !== null) {
            $mail->subject($template->subject ?? config('app.name'))
                ->line($template->render($this->data));
        } else {
            $mail->subject($this->type)->line((string) ($this->data['message'] ?? $this->type));
        }

        return $mail;
    }

    public function toDatabase(object $notifiable): array
    {
        return array_merge(['type' => $this->type], $this->data);
    }
}
