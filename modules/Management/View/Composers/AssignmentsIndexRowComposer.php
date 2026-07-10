<?php

declare(strict_types=1);

namespace Modules\Management\View\Composers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Modules\Management\Models\ManagementFunction;

/**
 * View Composer: management::event-assignments-index-row
 *
 * Provides:
 *   $mgmtBesetzungFunctions  → Collection<ManagementFunction>
 *   $mgmtBesetzungTasks      → Collection (stdClass: id, name) from event_tasks
 *   $mgmtBesetzungHasAny     → bool
 */
class AssignmentsIndexRowComposer
{
    public function compose(View $view): void
    {
        $empty = [
            'mgmtBesetzungFunctions' => collect(),
            'mgmtBesetzungTasks'     => collect(),
            'mgmtBesetzungHasAny'    => false,
        ];

        $event = $view->getData()['event']
            ?? request()->route('event')
            ?? null;

        if (! $event) {
            $view->with($empty);
            return;
        }

        $eventTasks = collect();
        if (Schema::hasTable('event_tasks')) {
            $eventTasks = DB::table('event_tasks')
                ->where('event_id', $event->id)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        }

        $fnIds = [];
        if (Schema::hasTable('management_functions') && Schema::hasTable('event_management_function')) {
            $fnIds = DB::table('event_management_function')
                ->where('event_id', $event->id)
                ->pluck('management_function_id')
                ->toArray();
        }

        $view->with([
            'mgmtBesetzungFunctions' => ! empty($fnIds)
                ? ManagementFunction::whereIn('id', $fnIds)->orderBy('name')->get()
                : collect(),
            'mgmtBesetzungTasks'  => $eventTasks,
            'mgmtBesetzungHasAny' => ! empty($fnIds) || $eventTasks->isNotEmpty(),
        ]);
    }
}
