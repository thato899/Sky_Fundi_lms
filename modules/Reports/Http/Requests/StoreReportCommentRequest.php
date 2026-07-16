<?php

declare(strict_types=1);

namespace Modules\Reports\Http\Requests;

use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreReportCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user()?->can('manageComments', $this->route('reportCard')) !== true) {
            return false;
        }
        if (in_array($this->input('comment_type'), ['academic_admin', 'principal'], true)) {
            $membership = $this->attributes->get('organization_membership');

            return $membership instanceof Membership && app(PermissionResolver::class)->allows($membership, 'reports.approve');
        }

        return true;
    }

    public function rules(): array
    {
        return ['organization_id' => ['prohibited'], 'overall_comment' => ['nullable', 'string', 'max:4000'], 'comment_type' => ['required_with:comment', Rule::in(['subject', 'class_teacher', 'academic_admin', 'principal', 'general'])], 'comment' => ['nullable', 'string', 'max:4000'], 'staff_profile_id' => ['nullable', 'uuid']];
    }
}
