<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use Core\Notifications\Infrastructure\Notifications\CoreNotification;
use Core\Queue\Domain\QueueName;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Tests\TestCase;

final class SchedulerAndQueueContractTest extends TestCase
{
    public function test_platform_maintenance_commands_keep_the_documented_frequencies(): void
    {
        $events = collect(app(Schedule::class)->events());
        $event = fn (string $command) => $events->first(fn ($event) => str_contains((string) $event->command, $command));

        $this->assertSame('0 * * * *', $event('platform:health-check')->expression);
        foreach (['platform:validate-licenses', 'platform:validate-subscriptions', 'platform:clean-temp', 'platform:clean-ai-cache'] as $command) {
            $this->assertSame('0 0 * * *', $event($command)->expression);
        }
        foreach (['platform:clean-queue', 'platform:backup'] as $command) {
            $this->assertSame('0 0 * * 0', $event($command)->expression);
        }
    }

    public function test_core_notifications_are_queued_on_the_notifications_queue(): void
    {
        $notification = new CoreNotification('test.notice', ['message' => 'Safe test message'], ['database']);

        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertSame(QueueName::Notifications->value, $notification->queue);
        $this->assertSame(['database'], $notification->via(new \stdClass));
    }
}
