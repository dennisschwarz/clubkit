<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

/**
 * Displays the activity log in the admin panel.
 *
 * Only read access is provided. Activity log entries are immutable by design:
 * they cannot be edited or deleted through the UI. Retention management
 * (pruning old entries) is handled via scheduled commands, not the interface.
 */
class ActivityLogController extends Controller
{
    /**
     * Number of entries displayed per page.
     */
    private const PER_PAGE = 50;

    /**
     * Renders the paginated, filterable activity log overview.
     *
     * Supported query filters:
     *   - causer_id  int     Filter by the user who caused the action
     *   - event      string  Filter by event type: created | updated | deleted
     *   - log_name   string  Filter by module log name (e.g. 'members', 'teams')
     *   - date_from  string  Y-m-d lower bound (inclusive)
     *   - date_to    string  Y-m-d upper bound (inclusive)
     *
     * @param  Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $query = Activity::with('causer')
            ->latest();

        if ($request->filled('causer_id')) {
            $query->where('causer_id', $request->integer('causer_id'))
                  ->where('causer_type', 'App\\Models\\User');
        }

        if ($request->filled('event')) {
            $query->where('event', $request->string('event')->value());
        }

        if ($request->filled('log_name')) {
            $query->inLog($request->string('log_name')->value());
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date('date_to'));
        }

        $activities = $query->paginate(self::PER_PAGE)->withQueryString();

        // Distinct log names for the module filter dropdown
        $logNames = Activity::distinct()->orderBy('log_name')->pluck('log_name');

        // Users list for the causer filter dropdown
        $users = User::orderBy('name')->get(['id', 'name']);

        return view('core::admin.activity-log.index', compact(
            'activities', 'logNames', 'users'
        ));
    }
}
