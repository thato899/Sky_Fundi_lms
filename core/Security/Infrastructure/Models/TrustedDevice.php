<?php

declare(strict_types=1);

namespace Core\Security\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TrustedDevice extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'trusted_devices';

    protected $fillable = ['user_id', 'fingerprint', 'device_name', 'ip_address', 'user_agent', 'trusted_at', 'last_seen_at', 'expires_at'];

    protected function casts(): array
    {
        return [
            'trusted_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
