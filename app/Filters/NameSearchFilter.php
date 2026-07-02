<?php

declare(strict_types=1);

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * Searches first_name AND last_name simultaneously (OR).
 *
 * Used for member lists where the name is split across two columns.
 * Both columns are searched case-insensitively.
 */
final class NameSearchFilter implements Filter
{
    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        $search = '%' . $value . '%';

        $query->where(function (Builder $q) use ($search): void {
            $q->where('first_name', 'like', $search)
              ->orWhere('last_name',  'like', $search);
        });
    }
}
