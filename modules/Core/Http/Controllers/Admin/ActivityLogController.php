<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Admin;

use App\Filters\DateFromFilter;
use App\Filters\DateToFilter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Renders the activity log for the admin area.
 *
 * Allowed filters (via ?filter[x]=...):
 *   filter[causer_id]  integer — filters by the user who caused the activity
 *   filter[event]      string  — exact match on the event name (created/updated/deleted)
 *   filter[log_name]   string  — filters by log channel (members/teams/events/…)
 *   filter[date_from]  date    — lower bound on created_at (Y-m-d)
 *   filter[date_to]    date    — upper bound on created_at (Y-m-d)
 *
 * Allowed sort fields (via ?sort=... | ?sort=-...):
 *   created_at (default DESC), event, log_name
 *
 * allowedFilters() and allowedSorts() accept variadic args — NO array wrapper.
 */
class ActivityLogController extends Controller
{
    /**
     * Number of activity entries displayed per page.
     */
    private const PER_PAGE = 50;

    /**
     * Display the paginated, filterable activity log.
     *
     * @param  Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $activities = QueryBuilder::for(Activity::with('causer'))
            ->allowedFilters(
                // causer_id must be combined with causer_type to avoid cross-model collisions
                AllowedFilter::callback('causer_id', function ($query, $value): void {
                    $query->where('causer_id', (int) $value)
                          ->where('causer_type', 'App\\Models\\User');
                }),
                AllowedFilter::exact('event'),
                AllowedFilter::scope('log_name', 'inLog'),
                AllowedFilter::custom('date_from', new DateFromFilter('created_at')),
                AllowedFilter::custom('date_to',   new DateToFilter('created_at')),
            )
            ->allowedSorts('created_at', 'event', 'log_name')
            ->defaultSort('-created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $logNames = Activity::distinct()->orderBy('log_name')->pluck('log_name');
        $users    = User::orderBy('name')->get(['id', 'name']);

        return view('core::admin.activity-log.index', compact(
            'activities', 'logNames', 'users'
        ));
    }
}
