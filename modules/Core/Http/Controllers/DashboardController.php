<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use App\Services\ModuleLoader;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Dashboard – Systemübersicht mit Schnellkennzahlen
 */
class DashboardController extends Controller
{
    public function __construct(private readonly ModuleLoader $moduleLoader) {}

    public function index(): View
    {
        $stats    = $this->buildStats();
        $modules  = $this->moduleLoader->getInstalled();

        return view('core::dashboard', compact('stats', 'modules'));
    }

    /**
     * Kennzahlen aus den installierten Modulen sammeln.
     * Jede Kachel wird nur gezeigt wenn die Tabelle existiert.
     */
    private function buildStats(): array
    {
        $stats = [];

        // Nutzer (immer vorhanden)
        $stats['users'] = [
            'label' => 'Nutzer',
            'value' => User::count(),
            'icon'  => '👤',
            'color' => '',
            'link'  => route('admin.users.index'),
        ];

        // Mitglieder (Members-Modul)
        if (Schema::hasTable('members')) {
            $membersTable = \Illuminate\Support\Facades\DB::table('members');
            $stats['members'] = [
                'label' => 'Mitglieder',
                'value' => $membersTable->count(),
                'icon'  => '🧑‍🤝‍🧑',
                'color' => '',
                'link'  => route('members.index'),
            ];
            $stats['eligible'] = [
                'label' => 'Spielberechtigt',
                'value' => $membersTable->where('eligible_to_play', true)->count(),
                'icon'  => '✅',
                'color' => 'ok',
                'link'  => route('members.index'),
            ];
        }

        // Teams (Teams-Modul)
        if (Schema::hasTable('teams')) {
            $teamsTable = \Illuminate\Support\Facades\DB::table('teams');
            $stats['teams'] = [
                'label' => 'Teams',
                'value' => $teamsTable->where('is_active', true)->count(),
                'icon'  => '⚽',
                'color' => '',
                'link'  => route('teams.index'),
            ];
        }

        return $stats;
    }
}
