<?php

declare(strict_types=1);

namespace Modules\Academics\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Academics\Domain\Enums\AcademicTermStatus;

final class AcademicTerm extends Model
{
    use HasUuidPrimaryKey;
    use SoftDeletes;

    protected $table = 'academics_academic_terms';

    protected $fillable = ['academic_year_id', 'term_number', 'name', 'start_date', 'end_date', 'status', 'is_current'];

    protected function casts(): array
    {
        return [
            'term_number' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => AcademicTermStatus::class,
            'is_current' => 'boolean',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
