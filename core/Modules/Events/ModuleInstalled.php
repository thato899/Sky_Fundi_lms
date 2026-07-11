<?php

declare(strict_types=1);

namespace Core\Modules\Events;

use Core\Modules\Infrastructure\Models\ModuleRegistration;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ModuleInstalled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ModuleRegistration $module,
    ) {}
}
