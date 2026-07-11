<?php

declare(strict_types=1);

namespace Core\Branding\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class BrandingChanged
{
    use Dispatchable;

    public function __construct(
        public readonly array $changedKeys,
    ) {}
}
