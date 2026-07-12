<?php

declare(strict_types=1);

namespace Core\Api\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Reusable filter/sort/paginate helpers implementing
 * docs/api/conventions.md#filtering-sorting-pagination
 * (`?filter[key]=value`, `?sort=-created_at`, `?page=&per_page=`) so
 * every controller doesn't hand-roll the same `->when()` chain. Purely
 * additive — existing controllers built before this helper existed
 * keep working exactly as they do today; this is for new/updated
 * controllers to opt into. See core/Api/README.md.
 */
final class ApiQueryBuilder
{
    /**
     * @param  string[]  $filterable  Whitelisted `filter[...]` keys — anything else in the request is silently ignored so callers can't filter on an unindexed or sensitive column.
     */
    public static function filter(Builder $query, Request $request, array $filterable): Builder
    {
        $filters = (array) $request->input('filter', []);

        foreach ($filters as $field => $value) {
            if (in_array($field, $filterable, true) && $value !== null && $value !== '') {
                $query->where($field, $value);
            }
        }

        return $query;
    }

    /**
     * @param  string[]  $sortable  Whitelisted sort keys.
     */
    public static function sort(Builder $query, Request $request, array $sortable, string $default = '-created_at'): Builder
    {
        $sort = (string) $request->input('sort', $default);
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (in_array($column, $sortable, true)) {
            $query->orderBy($column, $direction);
        }

        return $query;
    }

    public static function paginate(Builder $query, Request $request, int $defaultPerPage = 25, int $maxPerPage = 100): LengthAwarePaginator
    {
        $perPage = min((int) $request->integer('per_page', $defaultPerPage), $maxPerPage);

        return $query->paginate(max($perPage, 1));
    }
}
