<?php

declare(strict_types=1);

namespace Modules\Management\View\Composers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Modules\Management\Models\EventTask;

/**
 * View Composer: management::event-slots-panel
 *
 * Provides data for the shift plan tab (horizontal staffing schedule grid).
 *
 * Grid architecture:
 *   - Rows    = configured event-day tasks (slot_start_time / slot_end_time / slot_interval set)
 *   - Columns = union of all unique slot-start H:i labels across all configured tasks
 *   - Cells   = assigned members + capacity status per task × time-slot intersection
 *
 * Tasks without slot configuration appear in a separate "unconfigured" list below the grid.
 * Timed assignments are matched to grid cells by their time_from value (H:i format).
 *
 * Provides:
 *   $mgmtShiftTasks          → Collection<EventTask> (all event-day tasks, ordered by name)
 *   $mgmtShiftConfigured     → Collection<EventTask> (tasks with complete slot config)
 *   $mgmtShiftUnconfigured   → Collection<EventTask> (tasks missing slot config)
 *   $mgmtShiftTimeColumns    → list<string> sorted unique slot-start H:i labels
 *   $mgmtShiftGrid           → array<task_id, array<time_label, cell>>
 *                               cell: {time_from, time_to, capacity, assigned[]}
 *   $mgmtShiftSlotMap        → array<task_id, list<slot>> (raw assignments, backward compat)
 *   $mgmtShiftMembersJs      → array<id, {id, name}> for JS member pool in assign modal
 *   $mgmtShiftSlotConfigJs   → array<task_id, slot config> for JS modal pre-fill
 *   $mgmtShiftPriorityColors → array<string, string>
 *   $mgmtShiftPriorityLabels → array<string, string>
 */
class EventSlotsPanelComposer
{
    /** @var array<string, string> */
    private array $priorityColors = [
        'normal'    => 'gray',
        'important' => 'amber',
        'critical'  => 'red',
    ];

    /** @var array<string, string> */
    private array $priorityLabels = [
        'normal'    => 'Normal',
        'important' => 'Wichtig',
        'critical'  => 'Kritisch',
    ];

    public function compose(View $view): void
    {
        $empty = [
            'mgmtShiftTasks'          => collect(),
            'mgmtShiftConfigured'     => collect(),
            'mgmtShiftUnconfigured'   => collect(),
            'mgmtShiftTimeColumns'    => [],
            'mgmtShiftGrid'           => [],
            'mgmtShiftSkipCols'       => [],
            'mgmtShiftSlotMap'        => [],
            'mgmtShiftMembersJs'      => [],
            'mgmtShiftSlotConfigJs'   => [],
            'mgmtShiftPriorityColors' => $this->priorityColors,
            'mgmtShiftPriorityLabels' => $this->priorityLabels,
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

        $eventDate = $event->starts_at->toDateString();

        // ── All event-day tasks ───────────────────────────────────────────────
        // Event-day = no deadline, or deadline falls on the event date itself.

        $shiftTasks = EventTask::with('category')
            ->where('event_id', $event->id)
            ->where(function ($q) use ($eventDate) {
                $q->whereNull('deadline_at')
                  ->orWhereDate('deadline_at', '=', $eventDate);
            })
            ->orderBy('name')
            ->get();

        // ── Split: configured vs. unconfigured ───────────────────────────────

        $configured = $shiftTasks->filter(
            fn ($t) => $t->slot_start_time !== null
                && $t->slot_end_time !== null
                && $t->slot_interval_minutes !== null
        );

        $unconfigured = $shiftTasks->reject(
            fn ($t) => $t->slot_start_time !== null
                && $t->slot_end_time !== null
                && $t->slot_interval_minutes !== null
        );

        // ── Raw slot assignments (timed: time_from IS NOT NULL) ───────────────

        $slotMap = [];

        if ($shiftTasks->isNotEmpty() && Schema::hasTable('event_task_members')) {
            $taskIds = $shiftTasks->pluck('id')->toArray();

            $rows = DB::table('event_task_members')
                ->join('members', 'members.id', '=', 'event_task_members.member_id')
                ->whereIn('event_task_members.event_task_id', $taskIds)
                ->whereNotNull('event_task_members.time_from')
                ->orderBy('event_task_members.time_from')
                ->select(
                    'event_task_members.id',
                    'event_task_members.event_task_id',
                    'event_task_members.member_id',
                    'event_task_members.time_from',
                    'event_task_members.time_to',
                    DB::raw("CONCAT(members.last_name, ', ', members.first_name) AS member_name")
                )
                ->get();

            foreach ($rows as $row) {
                $slotMap[$row->event_task_id][] = [
                    'id'        => $row->id,
                    'member_id' => $row->member_id,
                    'name'      => $row->member_name,
                    'time_from' => Carbon::parse($row->time_from)->format('H:i'),
                    'time_to'   => Carbon::parse($row->time_to)->format('H:i'),
                ];
            }
        }

        // ── Build horizontal grid ─────────────────────────────────────────────
        // For each configured task: generate its time-slot columns and cells.
        // Time columns across all tasks are merged into one sorted global list.

        $timeColumnsMap = []; // H:i label → true (for deduplication and sorting)
        $grid           = []; // task_id → [time_label => cell array]

        // Smallest interval across all configured tasks — used as the colspan base unit.
        // Example: Task A = 30 min, Task B = 60 min → $minInterval = 30.
        // Task B cells then span colspan = 60/30 = 2 columns in the grid.
        $minInterval = $configured->isNotEmpty()
            ? (int) $configured->min('slot_interval_minutes')
            : 0;

        foreach ($configured as $task) {
            // MySQL TIME columns are returned as H:i:s strings; parse with seconds.
            $start    = Carbon::createFromFormat('H:i:s', $task->slot_start_time);
            $end      = Carbon::createFromFormat('H:i:s', $task->slot_end_time);
            $interval = (int) $task->slot_interval_minutes;
            $capacity = (int) ($task->slot_capacity ?? 1);
            // How many minimum-interval columns does one cell of this task occupy?
            // intdiv() avoids floating-point issues (all allowed intervals are multiples of 15).
            $colspan  = ($minInterval > 0) ? max(1, intdiv($interval, $minInterval)) : 1;

            $current = $start->copy();

            while ($current->lt($end)) {
                $label   = $current->format('H:i');
                $slotEnd = $current->copy()->addMinutes($interval);

                $timeColumnsMap[$label] = true;

                $grid[$task->id][$label] = [
                    'time_from' => $label,
                    'time_to'   => $slotEnd->format('H:i'),
                    'capacity'  => $capacity,
                    'assigned'  => [],
                    'colspan'   => $colspan,
                ];

                $current->addMinutes($interval);
            }
        }

        // Sort time columns chronologically (ksort works because H:i is lexicographically sortable).
        ksort($timeColumnsMap);
        $timeColumns = array_keys($timeColumnsMap);

        // Skip-column map: time labels that must be suppressed for a given task because
        // a preceding cell with colspan > 1 already covers them visually.
        // Example: task with 60-min interval, minInterval = 30 → colspan = 2.
        //   Cell "10:00" spans "10:30" as well → skipCols[taskId]["10:30"] = true.
        $skipCols = []; // taskId → [label => true]

        foreach ($configured as $task) {
            $interval = (int) $task->slot_interval_minutes;
            $colspan  = ($minInterval > 0) ? max(1, intdiv($interval, $minInterval)) : 1;

            if ($colspan <= 1) {
                continue; // No skip mapping needed when colspan is 1
            }

            foreach ($grid[$task->id] as $label => $cell) {
                $spanStart = Carbon::createFromFormat('H:i', $label);
                for ($i = 1; $i < $colspan; $i++) {
                    $skipLabel                       = $spanStart->copy()->addMinutes($minInterval * $i)->format('H:i');
                    $skipCols[$task->id][$skipLabel] = true;
                }
            }
        }

        // Fill assignment data into each matching grid cell (matched by time_from H:i label).
        foreach ($slotMap as $taskId => $slots) {
            foreach ($slots as $slot) {
                $label = $slot['time_from'];
                if (isset($grid[$taskId][$label])) {
                    $grid[$taskId][$label]['assigned'][] = $slot;
                }
            }
        }

        // ── Members JS data (for the assign-modal member select) ──────────────

        $membersJs = [];
        if (Schema::hasTable('members')) {
            foreach (DB::table('members')
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->orderBy('last_name')
                ->select('id', 'first_name', 'last_name')
                ->get() as $m) {
                $membersJs[$m->id] = [
                    'id'   => $m->id,
                    'name' => $m->last_name . ', ' . $m->first_name,
                ];
            }
        }

        // ── Slot config JS data (for pre-filling the config modal) ────────────
        // Keyed by task_id. Only tasks with at least slot_start_time set are included.

        $slotConfigJs = [];

        foreach ($shiftTasks as $task) {
            if ($task->slot_start_time !== null) {
                // Trim MySQL seconds from TIME columns (H:i:s → H:i).
                $slotConfigJs[$task->id] = [
                    'slot_start_time'       => substr((string) $task->slot_start_time, 0, 5),
                    'slot_end_time'         => substr((string) $task->slot_end_time, 0, 5),
                    'slot_interval_minutes' => $task->slot_interval_minutes,
                    'slot_capacity'         => $task->slot_capacity ?? 1,
                ];
            }
        }

        $view->with([
            'mgmtShiftTasks'          => $shiftTasks,
            'mgmtShiftConfigured'     => $configured,
            'mgmtShiftUnconfigured'   => $unconfigured,
            'mgmtShiftTimeColumns'    => $timeColumns,
            'mgmtShiftGrid'           => $grid,
            'mgmtShiftSkipCols'       => $skipCols,
            'mgmtShiftSlotMap'        => $slotMap,
            'mgmtShiftMembersJs'      => $membersJs,
            'mgmtShiftSlotConfigJs'   => $slotConfigJs,
            'mgmtShiftPriorityColors' => $this->priorityColors,
            'mgmtShiftPriorityLabels' => $this->priorityLabels,
        ]);
    }
}