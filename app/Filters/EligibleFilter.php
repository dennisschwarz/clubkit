<?php

declare(strict_types=1);

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * Filters members by playing eligibility.
 *
 * A member is eligible when eligible_to_play_date is set
 * AND the date is today or in the past.
 *
 * Value '1' → eligible members only
 * Value '0' → ineligible members only
 */
final class EligibleFilter implements Filter
{
    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        if ($value === '1') {
            $query->whereNotNull('eligible_to_play_date')
                  ->whereDate('eligible_to_play_date', '<=', Carbon::today());

            return;
        }

        $query->where(function (Builder $q): void {
            $q->whereNull('eligible_to_play_date')
              ->orWhereDate('eligible_to_play_date', '>', Carbon::today());
        });
    }
}
