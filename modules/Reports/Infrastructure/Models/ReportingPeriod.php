<?php

declare(strict_types=1);

namespace Modules\Reports\Infrastructure\Models;

use Carbon\Carbon;
use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Reports\Domain\Enums\ReportingPeriodStatus;

/**
 * @property string $id
 * @property string $uuid
 * @property string $organization_id
 * @property string $academic_year_id
 * @property string|null $academic_term_id
 * @property string $name
 * @property string|null $code
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property Carbon|null $result_cutoff_date
 * @property ReportingPeriodStatus $status
 */
final class ReportingPeriod extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['uuid', 'organization_id', 'academic_year_id', 'academic_term_id', 'name', 'code', 'start_date', 'end_date', 'result_cutoff_date', 'status', 'created_by', 'updated_by'];

    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'result_cutoff_date' => 'date', 'status' => ReportingPeriodStatus::class];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function reportCards(): HasMany
    {
        return $this->hasMany(ReportCard::class);
    }
}
