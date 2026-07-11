<?php

declare(strict_types=1);

namespace Core\AIGateway\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TestProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('core.ai.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string'],
            'prompt' => ['required', 'string', 'max:2000'],
        ];
    }
}
