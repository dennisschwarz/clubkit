<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Services\ModuleLoader;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

/**
 * Dashboard controller – system overview with quick-stats, recent activity
 * and module-specific summary sections.
 *
 * DB::table() is used throughout instead of Eloquent models so the dashboard
 * remains functional even when individual modules are not fully installed.
 * Schema::hasTable() guards prevent errors from missing tables.
 * moduleLoader->isActive() guards prevent route() calls for modules whose
 * ServiceProvider was not loaded.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly ModuleLoader $moduleLoader) {}

    public function index(): View
    {
        return view('core::dashboard', [
            'recentActivity'     => $this->buildRecentActivity(),
            'upcomingEvents'     => $this->buildUpcomingEvents(),
            'recentMembers'      => $this->buildRecentMembers(),
            'recentTransactions' => $this->buildRecentTransactions(),
        ]);
    }

    // ── Right column sections ─────────────────────────────────────────────────

    /**
     * Last 10 activity log entries with causer eager-loaded.
     * Uses the Activity Eloquent model so the properties JSON-collection cast
     * and the causer() morph relation are available in the view.
     */
    private function buildRecentActivity(): Collection
    {
        if (! Schema::hasTable('activity_log')) {
            return collect();
        }

        return Activity::with('causer')
            ->latest()
            ->take(10)
            ->get();
    }

    /** Next 3 upcoming events ordered by start date. */
    private function buildUpcomingEvents(): Collection
    {
        if (! $this->moduleLoader->isActive('events') || ! Schema::hasTable('events')) {
            return collect();
        }

        return DB::table('events')
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->take(3)
            ->select(['id', 'title', 'starts_at', 'location'])
            ->get();
    }

    /** Last 3 created members. Permission-gated: members.view required. */
    private function buildRecentMembers(): Collection
    {
        if (! auth()->user()->can('members.view')) {
            return collect();
        }
        if (! $this->moduleLoader->isActive('members') || ! Schema::hasTable('members')) {
            return collect();
        }

        return DB::table('members')
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->take(3)
            ->select(['id', 'first_name', 'last_name', 'status', 'created_at'])
            ->get();
    }

    /** Last 5 treasury transactions. Permission-gated: treasury.view required. */
    private function buildRecentTransactions(): Collection
    {
        if (! auth()->user()->can('treasury.view')) {
            return collect();
        }
        if (! $this->moduleLoader->isActive('treasury') || ! Schema::hasTable('treasury_transactions')) {
            return collect();
        }

        return DB::table('treasury_transactions')
            ->orderByDesc('transaction_date')
            ->take(5)
            ->select(['id', 'description', 'amount', 'type', 'transaction_date'])
            ->get();
    }
}