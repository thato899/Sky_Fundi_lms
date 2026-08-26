<?php

declare(strict_types=1);

namespace Modules\Materials\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TutorChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:2000'],
        ];
    }
}
