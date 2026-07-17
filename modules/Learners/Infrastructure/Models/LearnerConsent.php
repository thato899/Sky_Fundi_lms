<?php

declare(strict_types=1);

namespace Modules\Learners\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class LearnerConsent extends Model
{
    use HasUuidPrimaryKey;
    use SoftDeletes;

    protected $fillable = ['organization_id', 'learner_profile_id', 'guardian_profile_id', 'consent_type', 'status', 'recorded_date', 'expiry_date', 'recorded_by', 'notes'];

    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }

    protected function casts(): array
    {
        return ['recorded_date' => 'date', 'expiry_date' => 'date'];
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(GuardianProfile::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
