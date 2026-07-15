<?php

declare(strict_types=1);

namespace Modules\Staff\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class StaffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'employee_number' => $this->resource->getAttribute('employee_number'),
            'title' => $this->resource->getAttribute('title'),
            'first_name' => $this->resource->getAttribute('first_name'),
            'last_name' => $this->resource->getAttribute('last_name'),
            'email' => $this->resource->getAttribute('work_email'),
            'phone' => $this->resource->getAttribute('work_phone'),
            'staff_type' => $this->resource->getAttribute('staff_type'),
            'department_id' => $this->resource->getAttribute('department_id'),
            'employment_status' => $this->resource->getAttribute('employment_status'),
            'portal_access_enabled' => (bool) $this->resource->getAttribute('portal_access_enabled'),
            'created_at' => $this->resource->getAttribute('created_at'),
        ];
    }
}
