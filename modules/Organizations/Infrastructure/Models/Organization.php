<?php

declare(strict_types=1);

namespace Modules\Organizations\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Organizations\Domain\Enums\OrganizationStatus;

/**
 * @property string $id
 * @property string $name
 * @property string $timezone
 */
final class Organization extends Model
{
    use HasUuidPrimaryKey;
    use SoftDeletes;

    protected $fillable = ['name', 'code', 'type', 'status', 'registration_number', 'tax_number', 'email', 'telephone', 'website', 'address', 'country', 'province', 'city', 'postal_code', 'timezone', 'language', 'currency', 'storage_quota', 'current_storage', 'maximum_users', 'current_users', 'license_key', 'license_type', 'license_expires_at', 'license_renews_at', 'support_plan', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['status' => OrganizationStatus::class, 'license_expires_at' => 'date', 'license_renews_at' => 'date'];
    }

    public function administrators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_administrators')->withPivot(['assigned_by', 'assigned_at']);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(OrganizationSetting::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(OrganizationModule::class);
    }

    public function aiConfiguration(): HasOne
    {
        return $this->hasOne(OrganizationAiConfiguration::class);
    }
}
