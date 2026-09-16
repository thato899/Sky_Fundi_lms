<?php

declare(strict_types=1);

namespace Core\Security\Infrastructure\Models;

use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $user_id
 * @property string $secret
 * @property list<string> $recovery_codes
 * @property Carbon|null $confirmed_at
 */
final class TwoFactorAuthentication extends Model
{
    protected $table = 'two_factor_authentication';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'secret', 'recovery_codes', 'confirmed_at'];

    protected $hidden = ['secret', 'recovery_codes'];

    protected function casts(): array
    {
        return [
            'recovery_codes' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
