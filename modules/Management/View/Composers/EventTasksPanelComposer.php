<?php

declare(strict_types=1);

namespace Modules\Management\View\Composers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Modules\Management\Models\EventTask;
use Modules\Management\Models\EventTaskCategory;
use Modules\Management\Models\ManagementTask;

/**
 * View Composer: management::event-tasks-panel
 *
 * Provides all data needed for the tasks tab on the event detail page.
 *
 * Provides:
 *   $mgmtByCategory          → array<int|'allgemein', array{category, tasks, secDone, secTotal, secColor}>
 *   $mgmtAllgSection         → array{tasks, secDone, secTotal, secColor}
 *   $mgmtEventCategories     → Collection<EventTaskCategory>
 *   $mgmtMemberMap           → array<event_task_id, list<array{id, member_id, name, sort_order}>>
 *   $mgmtAvailableGlobalTasks → Collection<ManagementTask>
 *   $mgmtGlobalTasksJs       → list<array{id, name, priority}> for JS bridge
 *   $mgmtPriorityColors      → array<string, string>
 *   $mgmtPriorityLabels      → array<string, string>
 */
class EventTasksPanelComposer
{
    /** @var array<string, string> */
    private array $priorityColors = ['normal' => 'gray', 'important' => 'amber', 'critical' => 'red'];

    /** @var array<string, string> */
    private array $priorityLabels = ['normal' => 'Normal', 'important' => 'Wichtig', 'critical' => 'Kritisch'];

    public function compose(View $view): void
    {
        $empty = [
            'mgmtByCategory'           => [],
            'mgmtAllgSection'          => ['tasks' => [], 'secDone' => 0, 'secTotal' => 0, 'secColor' => 'gray'],
            'mgmtEventCategories'      => collect(),
            'mgmtMemberMap'            => [],
            'mgmtAvailableGlobalTasks' => collect(),
            'mgmtGlobalTasksJs'        => [],
            'mgmtPriorityColors'       => $this->priorityColors,
            'mgmtPriorityLabels'       => $this->priorityLabels,
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

        $eventTasks      = EventTask::with('category')
            ->where('event_id', $event->id)
            ->orderBy('sort_order')
            ->get();

        $eventCategories = EventTaskCategory::where('event_id', $event->id)
            ->orderBy('sort_order')
            ->get();

        // Build category sections (all categories shown, even empty).
        $byCategory    = [];
        $uncategorized = [];

        foreach ($eventCategories as $cat) {
            $byCategory[$cat->id] = [
                'category' => $cat,
                'tasks'    => [],
                'secDone'  => 0,
                'secTotal' => 0,
                'secColor' => 'gray',
            ];
        }

        foreach ($eventTasks as $task) {
            if ($task->category_id && isset($byCategory[$task->category_id])) {
                $byCategory[$task->category_id]['tasks'][] = $task;
            } elseif ($task->category_id && $task->category) {
                $byCategory[$task->category_id] = [
                    'category' => $task->category,
                    'tasks'    => [$task],
                    'secDone'  => 0,
                    'secTotal' => 1,
                    'secColor' => 'gray',
                ];
            } else {
                $uncategorized[] = $task;
            }
        }

        foreach ($byCategory as $catId => $sec) {
            $done  = (int) collect($sec['tasks'])->where('completed', true)->count();
            $total = count($sec['tasks']);
            $byCategory[$catId]['secDone']  = $done;
            $byCategory[$catId]['secTotal'] = $total;
            $byCategory[$catId]['secColor'] = $done === $total && $total > 0
                ? 'green'
                : ($done > 0 ? 'orange' : 'gray');
        }

        $allgDone    = (int) collect($uncategorized)->where('completed', true)->count();
        $allgTotal   = count($uncategorized);
        $allgSection = [
            'tasks'    => $uncategorized,
            'secDone'  => $allgDone,
            'secTotal' => $allgTotal,
            'secColor' => $allgDone === $allgTotal && $allgTotal > 0
                ? 'green'
                : ($allgDone > 0 ? 'orange' : 'gray'),
        ];

        // Member assignments per task (task-tab only: time_from IS NULL), ordered by sort_order.
        $memberMap = [];
        $taskIds   = $eventTasks->pluck('id')->toArray();

        if (! empty($taskIds) && Schema::hasTable('event_task_members')) {
            $etmRows = DB::table('event_task_members')
                ->join('members', 'members.id', '=', 'event_task_members.member_id')
                ->whereIn('event_task_members.event_task_id', $taskIds)
                ->whereNull('event_task_members.time_from')
                ->select(
                    'event_task_members.id',
                    'event_task_members.event_task_id',
                    'event_task_members.member_id',
                    'event_task_members.sort_order',
                    DB::raw("CONCAT(members.last_name, ', ', members.first_name) AS member_name")
                )
                ->orderBy('event_task_members.sort_order')
                ->get();

            foreach ($etmRows as $etm) {
                $memberMap[$etm->event_task_id][] = [
                    'id'         => $etm->id,
                    'member_id'  => $etm->member_id,
                    'name'       => $etm->member_name,
                    'sort_order' => $etm->sort_order,
                ];
            }
        }

        // Global tasks available for import.
        $availableGlobalTasks = collect();
        if (Schema::hasTable('management_tasks')) {
            $importedTemplateIds = ! empty($taskIds)
                ? DB::table('event_tasks')
                    ->where('event_id', $event->id)
                    ->whereNotNull('template_id')
                    ->pluck('template_id')
                    ->toArray()
                : [];

            $availableGlobalTasks = ManagementTask::with('category')
                ->whereNotIn('id', $importedTemplateIds)
                ->orderBy('name')
                ->get();
        }

        $globalTasksJs = [];
        foreach ($availableGlobalTasks as $mgmtT) {
            $globalTasksJs[] = [
                'id'       => $mgmtT->id,
                'name'     => $mgmtT->name,
                'priority' => $mgmtT->priority ?? 'normal',
            ];
        }

        $view->with([
            'mgmtByCategory'           => $byCategory,
            'mgmtAllgSection'          => $allgSection,
            'mgmtEventCategories'      => $eventCategories,
            'mgmtMemberMap'            => $memberMap,
            'mgmtAvailableGlobalTasks' => $availableGlobalTasks,
            'mgmtGlobalTasksJs'        => $globalTasksJs,
            'mgmtPriorityColors'       => $this->priorityColors,
            'mgmtPriorityLabels'       => $this->priorityLabels,
        ]);
    }
}
