<?php

declare(strict_types=1);

namespace Modules\Management\View\Composers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Modules\Management\Models\EventTask;
use Modules\Management\Models\ManagementFunction;

/**
 * View Composer: management::event-overview-panel
 *
 * Provides all data for the Übersicht tab:
 *   KPI tiles, category progress bars, prep-task Wochenplan,
 *   event-day staffing matrix, functions and teams.
 *
 * Provides:
 *   $mgmtKpiTotalTasks          → int
 *   $mgmtKpiDoneTasks           → int
 *   $mgmtKpiOpenTasks           → int
 *   $mgmtKpiUnstaffedPrep       → int
 *   $mgmtOverviewByCategory     → array<string, array{secDone, secTotal, unstaffedCount}>
 *   $mgmtOvFunctions            → array<array{name, member_name}>
 *   $mgmtOvTeams                → Collection (stdClass: id, name, color)
 *   $mgmtOvPrepByCategory       → array<string, list<array{name, deadline, priority, completed}>>
 *   $mgmtOvDayTasks             → list<array{id, name, completed}>
 *   $mgmtOvDayMatrix            → array<event_task_id, array<hour, list<array{name, initials}>>>
 *   $mgmtOvHours                → list<string>
 *   $mgmtOvWeekData             → list<array{label, range, days, members}>
 *   $mgmtOvActiveKwIdx          → int
 *   $mgmtOvUnstaffedPrepTasks   → list<string>
 */
class EventOverviewPanelComposer
{
    public function compose(View $view): void
    {
        $empty = [
            'mgmtKpiTotalTasks'          => 0,
            'mgmtKpiDoneTasks'           => 0,
            'mgmtKpiOpenTasks'           => 0,
            'mgmtKpiUnstaffedPrep'       => 0,
            'mgmtOverviewByCategory'     => [],
            'mgmtOvFunctions'            => [],
            'mgmtOvTeams'                => collect(),
            'mgmtOvPrepByCategory'       => [],
            'mgmtOvDayTasks'             => [],
            'mgmtOvDayMatrix'            => [],
            'mgmtOvHours'                => [],
            'mgmtOvWeekData'             => [],
            'mgmtOvActiveKwIdx'          => 0,
            'mgmtOvUnstaffedPrepTasks'   => [],
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

        $assignedTasks = EventTask::with('category')
            ->where('event_id', $event->id)
            ->orderBy('name')
            ->get();

        $assignedIds = $assignedTasks->pluck('id')->toArray();

        $mgmtKpiTotalTasks = count($assignedIds);
        $mgmtKpiDoneTasks  = (int) $assignedTasks->where('completed', true)->count();

        // ETM count per task for unstaffed detection.
        $etmCountByTask = [];
        if (Schema::hasTable('event_task_members') && ! empty($assignedIds)) {
            foreach (DB::table('event_task_members')
                ->whereIn('event_task_id', $assignedIds)
                ->select('event_task_id')
                ->get() as $er) {
                $etmCountByTask[$er->event_task_id] = ($etmCountByTask[$er->event_task_id] ?? 0) + 1;
            }
        }

        // Progress bars by category.
        $rawByCategory = [];
        foreach ($assignedTasks as $task) {
            $key = $task->category ? $task->category->name : 'Allgemein';
            $rawByCategory[$key][] = $task;
        }
        ksort($rawByCategory);

        $byCategory = [];
        foreach ($rawByCategory as $catName => $catTasks) {
            $done      = (int) collect($catTasks)->where('completed', true)->count();
            $total     = count($catTasks);
            $unstaffed = (int) collect($catTasks)
                ->filter(fn ($t) => ! $t->completed && ! isset($etmCountByTask[$t->id]))
                ->count();

            // catSection must match the data-section attribute on .ck-task-row elements
            // so that task-list.js can link overview progress bars to the task DOM without
            // a page reload (see updateAllProgress()).
            // Named categories use their integer ID as the section key; uncategorised = "allgemein".
            $firstTask  = $catTasks[0] ?? null;
            $catSection = ($catName === 'Allgemein' || ! ($firstTask?->category))
                ? 'allgemein'
                : (string) $firstTask->category->id;

            $byCategory[$catName] = [
                'catSection'     => $catSection,
                'secDone'        => $done,
                'secTotal'       => $total,
                'unstaffedCount' => $unstaffed,
            ];
        }

        // Functions with staffing status.
        $mgmtOvFunctions = $this->buildFunctions($event);

        // Teams.
        $mgmtOvTeams = collect();
        if (Schema::hasTable('event_team') && Schema::hasTable('teams')) {
            $teamIds = DB::table('event_team')
                ->where('event_id', $event->id)
                ->pluck('team_id')
                ->toArray();
            if (! empty($teamIds)) {
                $mgmtOvTeams = DB::table('teams')
                    ->whereIn('id', $teamIds)
                    ->orderBy('name')
                    ->select('id', 'name', 'color')
                    ->get();
            }
        }

        // Prep vs event-day task split.
        $mgmtOvPrepByCategory = [];
        $mgmtOvDayTasks       = [];
        $mgmtOvDayMatrix      = [];
        $mgmtOvHours          = [];
        $prepTaskList         = [];
        $eventDateStr         = $event->starts_at->toDateString();

        foreach ($assignedTasks as $task) {
            $deadlineAt = $task->deadline_at;
            $isPrep     = $deadlineAt !== null && $deadlineAt->toDateString() < $eventDateStr;

            if ($isPrep) {
                $catName = $task->category ? $task->category->name : 'Allgemein';
                $mgmtOvPrepByCategory[$catName][] = [
                    'name'      => $task->name,
                    'deadline'  => $deadlineAt->format('d.m.'),
                    'priority'  => $task->priority ?? 'normal',
                    'completed' => $task->completed,
                ];
                $prepTaskList[] = [
                    'id'        => $task->id,
                    'name'      => $task->name,
                    'completed' => $task->completed,
                    'priority'  => $task->priority ?? 'normal',
                    'deadline'  => $deadlineAt->toDateString(),
                ];
            } else {
                $mgmtOvDayTasks[] = [
                    'id'        => $task->id,
                    'name'      => $task->name,
                    'completed' => $task->completed,
                ];
            }
        }

        ksort($mgmtOvPrepByCategory);

        $mgmtOvUnstaffedPrepTasks = [];
        foreach ($prepTaskList as $pt) {
            if (! isset($etmCountByTask[$pt['id']])) {
                $mgmtOvUnstaffedPrepTasks[] = $pt['name'];
            }
        }

        $mgmtKpiUnstaffedPrep = count($mgmtOvUnstaffedPrepTasks);

        // Wochenplan grid.
        [$mgmtOvWeekData, $mgmtOvActiveKwIdx] = $this->buildWochenplan(
            $prepTaskList, $etmCountByTask, $eventDateStr, $event
        );

        // Staffing matrix for event-day tasks.
        if (! empty($mgmtOvDayTasks) && Schema::hasTable('event_task_members')) {
            [$mgmtOvHours, $mgmtOvDayMatrix] = $this->buildDayMatrix(
                $mgmtOvDayTasks, $event
            );
        }

        $view->with([
            'mgmtKpiTotalTasks'          => $mgmtKpiTotalTasks,
            'mgmtKpiDoneTasks'           => $mgmtKpiDoneTasks,
            'mgmtKpiOpenTasks'           => $mgmtKpiTotalTasks - $mgmtKpiDoneTasks,
            'mgmtKpiUnstaffedPrep'       => $mgmtKpiUnstaffedPrep,
            'mgmtOverviewByCategory'     => $byCategory,
            'mgmtOvFunctions'            => $mgmtOvFunctions,
            'mgmtOvTeams'                => $mgmtOvTeams,
            'mgmtOvPrepByCategory'       => $mgmtOvPrepByCategory,
            'mgmtOvDayTasks'             => $mgmtOvDayTasks,
            'mgmtOvDayMatrix'            => $mgmtOvDayMatrix,
            'mgmtOvHours'                => $mgmtOvHours,
            'mgmtOvWeekData'             => $mgmtOvWeekData,
            'mgmtOvActiveKwIdx'          => $mgmtOvActiveKwIdx,
            'mgmtOvUnstaffedPrepTasks'   => $mgmtOvUnstaffedPrepTasks,
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function buildFunctions(mixed $event): array
    {
        $result = [];
        if (! Schema::hasTable('management_functions') || ! Schema::hasTable('event_management_function')) {
            return $result;
        }

        $funcRows      = DB::table('event_management_function')->where('event_id', $event->id)->get();
        $assignedFnIds = $funcRows->pluck('management_function_id')->toArray();
        $functions     = ! empty($assignedFnIds)
            ? ManagementFunction::whereIn('id', $assignedFnIds)->orderBy('name')->get()->keyBy('id')
            : collect();

        $memberIds = $funcRows->pluck('member_id')->filter()->unique()->toArray();
        $members   = ! empty($memberIds) && Schema::hasTable('members')
            ? DB::table('members')->whereIn('id', $memberIds)->select('id', 'first_name', 'last_name')->get()->keyBy('id')
            : collect();

        foreach ($funcRows as $row) {
            $fn = $functions[$row->management_function_id] ?? null;
            if (! $fn) { continue; }
            $m = $row->member_id ? ($members[$row->member_id] ?? null) : null;
            $result[] = [
                'name'        => $fn->name,
                'member_name' => $m ? $m->last_name . ', ' . $m->first_name : null,
            ];
        }

        return $result;
    }

    private function buildWochenplan(array $prepTaskList, array $etmCountByTask, string $eventDateStr, mixed $event): array
    {
        $mgmtOvWeekData    = [];
        $mgmtOvActiveKwIdx = 0;

        if (! empty($prepTaskList) && Schema::hasTable('event_task_members')) {
            $prepTaskIds = array_column($prepTaskList, 'id');
            $prepEtms    = DB::table('event_task_members')
                ->join('members', 'members.id', '=', 'event_task_members.member_id')
                ->whereIn('event_task_members.event_task_id', $prepTaskIds)
                ->whereNull('event_task_members.time_from')
                ->select('event_task_members.event_task_id', 'event_task_members.member_id',
                    'members.last_name', 'members.first_name', 'members.profile_image')
                ->get();

            $byWeek   = [];
            foreach ($prepTaskList as $pt) {
                $date    = Carbon::parse($pt['deadline']);
                $weekKey = $date->isoWeekYear() . '-' . str_pad((string) $date->isoWeek(), 2, '0', STR_PAD_LEFT);
                $byWeek[$weekKey][] = $pt;
            }
            ksort($byWeek);

            $dayNames = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
            $kwIndex  = 0;
            foreach ($byWeek as $weekKey => $weekTasks) {
                $monday = Carbon::parse($weekTasks[0]['deadline'])->startOfWeek(Carbon::MONDAY);
                $days   = [];
                for ($i = 0; $i < 7; $i++) {
                    $d      = $monday->copy()->addDays($i);
                    $days[] = [
                        'wd'      => $dayNames[$i],
                        'short'   => $d->format('j.n.'),
                        'date'    => $d->toDateString(),
                        'isEvent' => $d->toDateString() === $eventDateStr,
                    ];
                }

                $weekTaskIds = array_column($weekTasks, 'id');
                $weekEtms    = $prepEtms->filter(fn ($e) => in_array($e->event_task_id, $weekTaskIds, true));

                $memberMap = [];
                foreach ($weekEtms as $etm) {
                    if (! isset($memberMap[$etm->member_id])) {
                        $memberMap[$etm->member_id] = [
                            'initials' => strtoupper(substr($etm->last_name, 0, 1))
                                        . strtoupper(substr($etm->first_name, 0, 1)),
                            'name'     => $etm->last_name,
                            'photo'    => $etm->profile_image,
                            'done'     => 0,
                            'total'    => 0,
                            'byDate'   => [],
                        ];
                    }
                    foreach ($weekTasks as $wt) {
                        if ($wt['id'] === $etm->event_task_id) {
                            $memberMap[$etm->member_id]['byDate'][$wt['deadline']][] = $wt;
                            $memberMap[$etm->member_id]['total']++;
                            if ($wt['completed']) { $memberMap[$etm->member_id]['done']++; }
                        }
                    }
                }

                $kwNum  = (int) substr($weekKey, 5);
                $endSun = $monday->copy()->addDays(6);
                $mgmtOvWeekData[] = [
                    'label'   => 'KW' . $kwNum,
                    'range'   => $monday->format('j.n.') . ' – ' . $endSun->format('j.n.'),
                    'days'    => $days,
                    'members' => array_values($memberMap),
                ];
                $kwIndex++;
            }

            $mgmtOvActiveKwIdx = max(0, count($mgmtOvWeekData) - 1);
        }

        // Stub weeks if no prep tasks produced week entries.
        if (empty($mgmtOvWeekData)) {
            $stubDayNames = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
            $eventMon     = Carbon::parse($eventDateStr)->startOfWeek(Carbon::MONDAY);
            $startMon     = Carbon::now()->startOfWeek(Carbon::MONDAY);

            if ($startMon->gt($eventMon)) {
                $startMon = $eventMon->copy();
            } elseif ($startMon->diffInWeeks($eventMon) > 4) {
                $startMon = $eventMon->copy()->subWeeks(4);
            }

            $cur = $startMon->copy();
            while ($cur->lte($eventMon)) {
                $stubDays = [];
                for ($i = 0; $i < 7; $i++) {
                    $d          = $cur->copy()->addDays($i);
                    $stubDays[] = [
                        'wd'      => $stubDayNames[$i],
                        'short'   => $d->format('j.n.'),
                        'date'    => $d->toDateString(),
                        'isEvent' => $d->toDateString() === $eventDateStr,
                    ];
                }
                $endSun           = $cur->copy()->addDays(6);
                $mgmtOvWeekData[] = [
                    'label'   => 'KW' . $cur->isoWeek(),
                    'range'   => $cur->format('j.n.') . ' – ' . $endSun->format('j.n.'),
                    'days'    => $stubDays,
                    'members' => [],
                ];
                $cur->addWeek();
            }

            $mgmtOvActiveKwIdx = max(0, count($mgmtOvWeekData) - 1);
        }

        return [$mgmtOvWeekData, $mgmtOvActiveKwIdx];
    }

    private function buildDayMatrix(array $mgmtOvDayTasks, mixed $event): array
    {
        $mgmtOvHours     = [];
        $mgmtOvDayMatrix = [];

        $dayTaskIds = array_column($mgmtOvDayTasks, 'id');
        $daySlots   = DB::table('event_task_members')
            ->join('members', 'members.id', '=', 'event_task_members.member_id')
            ->whereIn('event_task_members.event_task_id', $dayTaskIds)
            ->whereNotNull('event_task_members.time_from')
            ->select('event_task_members.event_task_id', 'event_task_members.time_from',
                'event_task_members.time_to', 'members.last_name', 'members.first_name')
            ->orderBy('event_task_members.time_from')
            ->get();

        if ($daySlots->isNotEmpty()) {
            // Collect every unique slot-start label in H:i format from actual assignments.
            // Using the exact time_from values preserves the configured interval granularity
            // (e.g. 30-min slots → 17:00 / 17:30, not a coarse hourly 17:00 / 18:00 grid).
            $timeLabelMap = [];
            foreach ($daySlots as $s) {
                $label                = Carbon::parse($s->time_from)->format('H:i');
                $timeLabelMap[$label] = true;
            }
            ksort($timeLabelMap);
            $mgmtOvHours = array_keys($timeLabelMap);

            foreach ($mgmtOvDayTasks as $dt) {
                $mgmtOvDayMatrix[$dt['id']] = array_fill_keys($mgmtOvHours, []);
            }

            foreach ($daySlots as $slot) {
                // Each assignment maps to exactly one column: its time_from label.
                $slotKey  = Carbon::parse($slot->time_from)->format('H:i');
                $initials = strtoupper(substr($slot->last_name, 0, 1))
                          . strtoupper(substr($slot->first_name, 0, 1));
                if (isset($mgmtOvDayMatrix[$slot->event_task_id][$slotKey])) {
                    $mgmtOvDayMatrix[$slot->event_task_id][$slotKey][] = [
                        'name'     => $slot->last_name . ', ' . $slot->first_name,
                        'initials' => $initials,
                    ];
                }
            }
        } elseif ($event->starts_at && $event->ends_at) {
            // Fallback: use event hours so the matrix always renders.
            $startH = (int) $event->starts_at->format('H');
            $endH   = (int) $event->ends_at->format('H');
            for ($h = $startH; $h <= $endH; $h++) {
                $mgmtOvHours[] = str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':00';
            }
            foreach ($mgmtOvDayTasks as $dt) {
                $mgmtOvDayMatrix[$dt['id']] = array_fill_keys($mgmtOvHours, []);
            }
        }

        return [$mgmtOvHours, $mgmtOvDayMatrix];
    }
}