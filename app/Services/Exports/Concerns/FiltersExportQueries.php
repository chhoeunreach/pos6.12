<?php

namespace App\Services\Exports\Concerns;

use Illuminate\Database\Query\Builder;

trait FiltersExportQueries
{
    protected function applyDateFilter(Builder $query, array $filters, string $column): void
    {
        if (! empty($filters['date_from'])) {
            $query->whereDate($column, '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate($column, '<=', $filters['date_to']);
        }
    }

    protected function applyLocationFilter(Builder $query, array $filters, string $column = 'location_id'): void
    {
        if (! empty($filters['location_id'])) {
            $query->where($column, (int) $filters['location_id']);
        }
    }
}
