<?php

declare(strict_types=1);

namespace Modules\Organizations\Infrastructure\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Organizations\Infrastructure\Models\Organization;

/** Persistence queries are kept here so application services do not duplicate filtering rules. */
final class OrganizationRepository
{
    public function paginate(array $filters, ?string $administratorId = null): LengthAwarePaginator
    {
        return Organization::query()
            ->when($administratorId, fn ($query) => $query->whereHas('administrators', fn ($administrators) => $administrators->whereKey($administratorId)))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }
}
