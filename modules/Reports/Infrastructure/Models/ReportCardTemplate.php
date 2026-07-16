<?php

declare(strict_types=1);

namespace Modules\Reports\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $uuid
 * @property string $organization_id
 * @property bool $is_active
 * @property bool $is_default
 * @property string $page_size
 * @property bool $show_attendance
 */
final class ReportCardTemplate extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['uuid', 'organization_id', 'name', 'description', 'is_active', 'is_default', 'show_attendance', 'show_assessment_breakdown', 'show_subject_comments', 'show_overall_comment', 'show_grading_legend', 'show_learner_photo', 'show_organization_logo', 'footer_text', 'page_size', 'created_by', 'updated_by'];

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
        return ['is_active' => 'boolean', 'is_default' => 'boolean', 'show_attendance' => 'boolean', 'show_assessment_breakdown' => 'boolean', 'show_subject_comments' => 'boolean', 'show_overall_comment' => 'boolean', 'show_grading_legend' => 'boolean', 'show_learner_photo' => 'boolean', 'show_organization_logo' => 'boolean'];
    }
}
