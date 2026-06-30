<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use App\Services\ModuleLoader;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Dashboard controller – system overview with quick-stats tiles.
 *
 * Important: DB::table() is used instead of Eloquent models so the dashboard
 * remains functional even when individual modules are not installed.
 *
 * Schema::hasTable() guards prevent errors from missing tables.
 * moduleLoader->isActive() guards prevent route() calls for modules whose
 * ServiceProvider was not loaded (routes are only registered for active modules).
 * A module's table can exist even when the module is not active (e.g. after a
 * direct migration run without going through the module installer).
 */
class DashboardController extends Controller
{
    /**
     * @param ModuleLoader $moduleLoader
     */
    public function __construct(private readonly ModuleLoader $moduleLoader) {}

    /**
     * @return View
     */
    public function index(): View
    {
        $stats   = $this->buildStats();
        $modules = $this->moduleLoader->getInstalled();

        return view('core::dashboard', compact('stats', 'modules'));
    }

    /**
     * Collects stat tiles from all installed and active modules.
     *
     * Each tile is only included when both conditions are met:
     *  1. The module is active (its ServiceProvider is loaded, routes are registered).
     *  2. The backing database table exists (defensive guard against stale state).
     *
     * @return array<string, array{label: string, value: int, icon: string, color: string, link: string}>
     */
    private function buildStats(): array
    {
        $stats = [];

        // Users are always present (core table)
        $stats['users'] = [
            'label' => 'Nutzer',
            'value' => User::count(),
            'icon'  => '👤',
            'color' => '',
            'link'  => route('admin.users.index'),
        ];

        // Members module – only when module is active AND table exists.
        // moduleLoader->isActive() ensures route('members.index') is registered.
        if ($this->moduleLoader->isActive('members') && Schema::hasTable('members')) {
            $stats['members'] = [
                'label' => 'Mitglieder',
                'value' => DB::table('members')->whereNull('deleted_at')->count(),
                'icon'  => '🧑‍🤝‍🧑',
                'color' => '',
                'link'  => route('members.index'),
            ];

            // Eligible to play = eligible_to_play_date is set AND is not in the future.
            // Replaced the old boolean `eligible_to_play` column after migration 2026_06_26_000002.
            $stats['eligible'] = [
                'label' => 'Spielberechtigt',
                'value' => DB::table('members')
                    ->whereNull('deleted_at')
                    ->whereNotNull('eligible_to_play_date')
                    ->whereDate('eligible_to_play_date', '<=', now())
                    ->count(),
                'icon'  => '✅',
                'color' => 'ok',
                'link'  => route('members.index'),
            ];
        }

        // Teams module – only when module is active AND table exists.
        if ($this->moduleLoader->isActive('teams') && Schema::hasTable('teams')) {
            $stats['teams'] = [
                'label' => 'Teams',
                'value' => DB::table('teams')->where('is_active', true)->count(),
                'icon'  => '⚽',
                'color' => '',
                'link'  => route('teams.index'),
            ];
        }

        return $stats;
    }
}
