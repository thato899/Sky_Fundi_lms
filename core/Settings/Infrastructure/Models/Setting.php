<?php

declare(strict_types=1);

namespace Core\Settings\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A single database-driven configuration value. Never read directly —
 * always through Core\Settings\Application\SettingsService, which adds
 * caching and encryption handling. See core/Settings/README.md.
 */
final class Setting extends Model
{
    use HasUuids;

    protected $table = 'settings';

    protected $fillable = ['key', 'group', 'value', 'is_encrypted'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_encrypted' => 'boolean',
        ];
    }
}
