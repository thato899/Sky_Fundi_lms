<?php

declare(strict_types=1);

namespace Modules\EnterpriseOperations\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Organizations\Infrastructure\Models\Organization;

final class Campus extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'enterprise_campuses';
    protected $fillable = ['organization_id', 'code', 'name', 'timezone', 'address', 'settings', 'is_active'];
    protected function casts(): array { return ['address' => 'array', 'settings' => 'array', 'is_active' => 'boolean']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
}
