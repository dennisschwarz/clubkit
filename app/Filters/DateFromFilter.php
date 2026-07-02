<?php

declare(strict_types=1);

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * Generic lower-bound date filter: WHERE date_column >= value.
 *
 * The column to filter on is configurable via the constructor.
 * Default: 'created_at'.
 *
 * Usage: AllowedFilter::custom('date_from', new DateFromFilter('transaction_date'))
 */
final class DateFromFilter implements Filter
{
    public function __construct(
        private readonly string $column = 'created_at'
    ) {}

    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        $query->whereDate($this->column, '>=', $value);
    }
}
