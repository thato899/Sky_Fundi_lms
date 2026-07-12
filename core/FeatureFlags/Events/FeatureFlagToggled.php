<?php

declare(strict_types=1);

namespace Core\FeatureFlags\Events;

use Core\FeatureFlags\Infrastructure\Models\FeatureFlag;
use Core\Support\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class FeatureFlagToggled implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly FeatureFlag $flag,
        public readonly ?string $scopeType,
        public readonly ?string $scopeId,
        public readonly bool $enabled,
    ) {}

    public function auditAction(): string
    {
        return 'feature_flag.toggled';
    }

    public function auditTarget(): ?Model
    {
        return $this->flag;
    }

    public function auditContext(): array
    {
        return ['after' => [
            'scope_type' => $this->scopeType ?? 'global',
            'scope_id' => $this->scopeId,
            'enabled' => $this->enabled,
        ]];
    }
}
