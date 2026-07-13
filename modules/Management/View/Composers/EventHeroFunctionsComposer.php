<?php

declare(strict_types=1);

namespace Modules\Management\View\Composers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * View Composer: management::event-hero-functions
 *
 * Merges both function sources (Option C) for the hero right column:
 *   1. Club functions   — event_management_function + management_functions
 *   2. Ad-hoc functions — event_functions
 *
 * Provides: $heroFunctions → list<array{name: string, member_name: string|null}>
 */
class EventHeroFunctionsComposer
{
    public function compose(View $view): void
    {
        $data          = $view->getData();
        $event         = $data['event']         ?? null;
        $showFunctions = $data['showFunctions'] ?? false;

        if (! $event || ! $showFunctions) {
            $view->with('heroFunctions', []);
            return;
        }

        $heroFunctions = [];

        // ── 1. Club functions (event_management_function) ─────────────────────

        if (Schema::hasTable('event_management_function')) {
            $clubRows = DB::table('event_management_function')
                ->join('management_functions',
                    'management_functions.id', '=',
                    'event_management_function.management_function_id')
                ->leftJoin('members',
                    'members.id', '=',
                    'event_management_function.member_id')
                ->where('event_management_function.event_id', '=', $event->id)
                ->orderBy('management_functions.name')
                ->select([
                    'management_functions.name',
                    'members.first_name',
                    'members.last_name',
                ])
                ->get();

            foreach ($clubRows as $row) {
                $heroFunctions[] = [
                    'name'        => $row->name,
                    'member_name' => $row->first_name
                        ? trim($row->first_name . ' ' . $row->last_name)
                        : null,
                ];
            }
        }

        // ── 2. Ad-hoc event functions (event_functions) ───────────────────────

        if (Schema::hasTable('event_functions')) {
            $adHocRows = DB::table('event_functions')
                ->leftJoin('members', 'members.id', '=', 'event_functions.member_id')
                ->where('event_functions.event_id', '=', $event->id)
                ->orderBy('event_functions.name')
                ->select([
                    'event_functions.name',
                    'members.first_name',
                    'members.last_name',
                ])
                ->get();

            foreach ($adHocRows as $row) {
                $heroFunctions[] = [
                    'name'        => $row->name,
                    'member_name' => $row->first_name
                        ? trim($row->first_name . ' ' . $row->last_name)
                        : null,
                ];
            }
        }

        $view->with('heroFunctions', $heroFunctions);
    }
}
