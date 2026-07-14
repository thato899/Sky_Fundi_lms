<?php

declare(strict_types=1);

namespace Core\Deployment\Http\Resources;

use Core\Deployment\Infrastructure\Models\DeploymentProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DeploymentProfile
 */
final class DeploymentProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'strategy' => $this->strategy->value,
            'database_config' => $this->database_config,
            'storage_config' => $this->storage_config,
            'branding_config' => $this->branding_config,
            'environment_config' => $this->environment_config,
            'ai_provider' => $this->ai_provider,
            'modules' => $this->modules,
            'administrator_id' => $this->administrator_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
