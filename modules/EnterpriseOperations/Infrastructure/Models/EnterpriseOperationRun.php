<?php

declare(strict_types=1);

namespace Modules\EnterpriseOperations\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

final class EnterpriseOperationRun extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['organization_id', 'operation_type', 'status', 'payload', 'preview', 'result', 'requested_by', 'approved_by', 'approved_at', 'executed_at', 'rolled_back_at'];
    protected function casts(): array { return ['payload' => 'array', 'preview' => 'array', 'result' => 'array', 'approved_at' => 'datetime', 'executed_at' => 'datetime', 'rolled_back_at' => 'datetime']; }
}
