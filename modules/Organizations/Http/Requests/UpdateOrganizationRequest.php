<?php

declare(strict_types=1);

namespace Modules\Organizations\Http\Requests;

use Illuminate\Validation\Rule;

final class UpdateOrganizationRequest extends StoreOrganizationRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['name'][0] = 'sometimes'; $rules['code'][0] = 'sometimes'; $rules['code'][4] = Rule::unique('organizations', 'code')->ignore($this->route('organization'));
        $rules['type'][0] = 'sometimes';
        return $rules;
    }
}
