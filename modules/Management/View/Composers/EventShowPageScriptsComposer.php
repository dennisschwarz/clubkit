<?php

declare(strict_types=1);

namespace Modules\Management\View\Composers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Modules\Management\Models\ManagementFunction;
use Modules\Management\Models\ManagementTask;

/**
 * View Composer: management::event-show-page-scripts
 *
 * Provides the JS data bridge consumed by events-detail.js:
 *
 *   $mgmtAvailableTasksJs     → global tasks not yet imported (for "import from library" dropdown)
 *   $mgmtCategoriesJs         → event_task_categories for this event (category dropdown in task form)
 *   $mgmtShiftTasksJs         → event-day tasks (for shift plan config-modal task dropdown)
 *   $mgmtAvailableFunctionsJs → global functions not yet assigned (for add-function modal)
 *   $mgmtShiftSlotConfigJs    → slot config per task id (for pre-filling the config modal)
 */
class EventShowPageScriptsComposer
{
    public function compose(View $view): void
    {
        $empty = [
            'mgmtAvailableTasksJs'    => [],
            'mgmtCategoriesJs'        => [],
            'mgmtShiftTasksJs'        => [],
            'mgmtAvailableFunctionsJs' => [],
            'mgmtShiftSlotConfigJs'   => [],
        ];

        if (! Schema::hasTable('event_tasks')) {
            $view->with($empty);
            return;
        }

        $event = $view->getData()['event']
            ?? request()->route('event')
            ?? null;

        if (! $event) {
            $view->with($empty);
            return;
        }

        $importedTemplateIds = DB::table('event_tasks')
            ->where('event_id', $event->id)
            ->whereNotNull('template_id')
            ->pluck('template_id')
            ->toArray();

        $mgmtAvailableTasksJs = [];
        if (Schema::hasTable('management_tasks')) {
            foreach (ManagementTask::with('category')
                ->whereNotIn('id', $importedTemplateIds)
                ->orderBy('name')
                ->get() as $task) {
                $mgmtAvailableTasksJs[$task->id] = [
                    'id'       => $task->id,
                    'name'     => $task->name,
                    'category' => $task->category?->name ?? '',
                    'priority' => $task->priority ?? 'normal',
                ];
            }
        }

        $mgmtCategoriesJs = [];
        foreach (DB::table('event_task_categories')
            ->where('event_id', $event->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get() as $cat) {
            $mgmtCategoriesJs[$cat->id] = [
                'id'    => $cat->id,
                'name'  => $cat->name,
                'color' => $cat->color ?? null,
            ];
        }

        $mgmtShiftTasksJs = [];
        $eventDate        = $event->starts_at->toDateString();
        foreach (DB::table('event_tasks')
            ->where('event_id', $event->id)
            ->where(function ($q) use ($eventDate) {
                $q->whereNull('deadline_at')
                  ->orWhereDate('deadline_at', '=', $eventDate);
            })
            ->orderBy('name')
            ->select('id', 'name')
            ->get() as $row) {
            $mgmtShiftTasksJs[$row->id] = ['id' => $row->id, 'name' => $row->name];
        }

        $mgmtAvailableFunctionsJs = [];
        if (Schema::hasTable('management_functions') && Schema::hasTable('event_management_function')) {
            $assignedFnIds = DB::table('event_management_function')
                ->where('event_id', $event->id)
                ->pluck('management_function_id')
                ->toArray();

            foreach (ManagementFunction::orderBy('name')->get() as $fn) {
                if (! in_array($fn->id, $assignedFnIds, true)) {
                    $mgmtAvailableFunctionsJs[$fn->id] = ['id' => $fn->id, 'name' => $fn->name];
                }
            }
        }

        // ── Slot config per task (for shift plan config-modal pre-fill) ──────────
        // Only populated when the slot_start_time column exists (after migration).

        $mgmtShiftSlotConfigJs = [];

        if (
            Schema::hasTable('event_tasks')
            && Schema::hasColumn('event_tasks', 'slot_start_time')
        ) {
            foreach (DB::table('event_tasks')
                ->where('event_id', $event->id)
                ->whereNotNull('slot_start_time')
                ->select('id', 'slot_start_time', 'slot_end_time', 'slot_interval_minutes', 'slot_capacity')
                ->get() as $row) {
                $mgmtShiftSlotConfigJs[$row->id] = [
                    'slot_start_time'       => substr((string) $row->slot_start_time, 0, 5),
                    'slot_end_time'         => substr((string) $row->slot_end_time, 0, 5),
                    'slot_interval_minutes' => (int) $row->slot_interval_minutes,
                    'slot_capacity'         => (int) ($row->slot_capacity ?? 1),
                ];
            }
        }

        $view->with([
            'mgmtAvailableTasksJs'    => $mgmtAvailableTasksJs,
            'mgmtCategoriesJs'        => $mgmtCategoriesJs,
            'mgmtShiftTasksJs'        => $mgmtShiftTasksJs,
            'mgmtAvailableFunctionsJs' => $mgmtAvailableFunctionsJs,
            'mgmtShiftSlotConfigJs'   => $mgmtShiftSlotConfigJs,
        ]);
    }
}