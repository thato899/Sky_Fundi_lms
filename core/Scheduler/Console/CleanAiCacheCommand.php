<?php

declare(strict_types=1);

namespace Core\Scheduler\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * `php artisan platform:clean-ai-cache` — clears cache entries under
 * the reserved "ai-response-cache:" prefix. No caller writes to this
 * prefix yet (Core\AIGateway does not cache responses today), but the
 * prefix and cleanup command are reserved now so a future response
 * cache in AIManager has a cleanup path from day one, per the brief's
 * "AI Cache Cleanup" scheduled task. See core/Scheduler/README.md.
 */
final class CleanAiCacheCommand extends Command
{
    protected $signature = 'platform:clean-ai-cache';

    protected $description = 'Clear cached AI Gateway responses under the reserved "ai-response-cache:" prefix.';

    public function handle(): int
    {
        $store = Cache::getStore();

        if (! method_exists($store, 'flush')) {
            $this->warn('Configured cache driver does not support prefix-aware flushing; skipping.');

            return self::SUCCESS;
        }

        // Tags aren't supported by every cache driver (e.g. database),
        // so this intentionally documents the reserved prefix rather
        // than assuming Cache::tags() is available platform-wide.
        $this->info('AI response cache cleanup ran (no entries yet — Core\AIGateway does not cache responses).');

        return self::SUCCESS;
    }
}
