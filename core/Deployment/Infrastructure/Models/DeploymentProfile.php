<?php

declare(strict_types=1);

namespace Core\Deployment\Infrastructure\Models;

use Core\Deployment\Domain\Enums\DeploymentStrategy;
use Core\Support\Traits\HasMetadata;
use Core\Support\Traits\HasUuidPrimaryKey;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Structured deployment configuration for a subject (platform-wide
 * when null, a future Organization once that model exists) — see
 * core/Deployment/README.md. No automation reads or acts on this yet;
 * it is a record of intended configuration, not an executor.
 */
final class DeploymentProfile extends Model
{
    use HasMetadata;
    use HasUuidPrimaryKey;
    use SoftDeletes;

    protected $table = 'deployment_profiles';

    protected $fillable = [
        'subject_type', 'subject_id', 'strategy', 'database_config',
        'storage_config', 'branding_config', 'environment_config',
        'ai_provider', 'modules', 'administrator_id', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'strategy' => DeploymentStrategy::class,
            'database_config' => 'array',
            'storage_config' => 'array',
            'branding_config' => 'array',
            'environment_config' => 'array',
            'modules' => 'array',
            'metadata' => 'array',
        ];
    }

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administrator_id');
    }
}
