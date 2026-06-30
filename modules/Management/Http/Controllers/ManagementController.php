<?php

declare(strict_types=1);

namespace Modules\Management\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Repositories\CustomFieldRepository;
use App\Services\ModuleLoader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Management\Http\Requests\StoreFunctionRequest;
use Modules\Management\Http\Requests\StoreTaskRequest;
use Modules\Management\Http\Requests\UpdateFunctionRequest;
use Modules\Management\Http\Requests\UpdateTaskRequest;
use Modules\Management\Models\ManagementFunction;
use Modules\Management\Models\ManagementTask;
use Modules\Management\Models\ManagementTaskCategory;
use Modules\Members\Models\Member;

/**
 * Handles the Management module's main index page and all CRUD operations
 * for management functions and tasks.
 *
 * ── Architecture note ─────────────────────────────────────────────────────────
 *
 * Management has NO direct PHP dependency on the Teams module.
 * Teams integration is handled entirely via the hook system:
 *
 *   TeamsServiceProvider::boot() → registers hooks into management.function.list,
 *   management.task.list, management.*.modal.teams, management.page.scripts
 *
 * When Teams is not installed, Management shows all entries in a flat list
 * (no team filter, no team grouping). When Teams is installed, TeamsServiceProvider
 * injects the appropriate UI without Management knowing about it.
 *
 * Team ID synchronisation in management_function_team and management_task_team
 * is done via DB::table() directly. Management owns these pivot tables
 * (module.json → tables[]) and is allowed to write to them directly
 * without importing any Team model.
 */
class ManagementController extends Controller
{
    /**
     * @param  ModuleLoader          $moduleLoader
     * @param  CustomFieldRepository $cfRepository
     */
    public function __construct(
        private readonly ModuleLoader          $moduleLoader,
        private readonly CustomFieldRepository $cfRepository
    ) {}

    /**
     * Renders the management overview with functions, tasks, and all JS data bridges.
     *
     * Team-related data is not loaded here. The Teams module injects its own
     * data (filter, grouping, JS bridge) via hook views.
     *
     * @return View
     */
    public function index(): View
    {
        $membersActive = $this->moduleLoader->isActive('members');

        // Load functions + tasks without Teams — the teams() relation is only called
        // from Teams hook views, which only run when the Teams module is active.
        $functions  = ManagementFunction::with(['members', 'creator'])->orderBy('name')->get();
        $tasks      = ManagementTask::with(['members', 'creator', 'category'])->orderBy('name')->get();
        $members    = $membersActive
            ? Member::orderBy('last_name')->orderBy('first_name')->get()
            : collect();
        $categories = ManagementTaskCategory::orderBy('name')->get();

        // ── JS data bridges ───────────────────────────────────────────────────
        // No fn()/arrow functions in map() – foreach is required for @json() compatibility.
        // team_ids are NOT set here — Teams injects them via the
        // management.page.scripts hook (window.CK_Teams.functionTeamIds).

        $functionsJs  = [];
        $tasksJs      = [];
        $membersJs    = [];
        $categoriesJs = [];

        foreach ($functions as $fn) {
            $functionsJs[$fn->id] = [
                'id'         => $fn->id,
                'name'       => $fn->name,
                'member_ids' => $fn->members->pluck('id')->values()->toArray(),
            ];
        }

        foreach ($tasks as $task) {
            $tasksJs[$task->id] = [
                'id'          => $task->id,
                'name'        => $task->name,
                'description' => $task->description ?? '',
                'category_id' => $task->category_id,
                'priority'    => $task->priority ?? 'normal',
                'member_ids'  => $task->members->pluck('id')->values()->toArray(),
            ];
        }

        foreach ($members as $member) {
            $membersJs[$member->id] = $member->toJsOption();
        }

        foreach ($categories as $cat) {
            $categoriesJs[$cat->id] = ['id' => $cat->id, 'name' => $cat->name];
        }

        $cfData = $this->cfRepository->loadForObjectTypes([
            'management_function',
            'management_task',
        ]);

        $mgmtFunctionCfDefs   = $cfData['management_function']['defs'];
        $mgmtFunctionCfValues = $cfData['management_function']['values'];
        $mgmtTaskCfDefs       = $cfData['management_task']['defs'];
        $mgmtTaskCfValues     = $cfData['management_task']['values'];

        return view('management::index', compact(
            'functions', 'tasks', 'members', 'categories',
            'functionsJs', 'tasksJs', 'membersJs', 'categoriesJs',
            'membersActive',
            'mgmtFunctionCfDefs', 'mgmtFunctionCfValues',
            'mgmtTaskCfDefs',     'mgmtTaskCfValues'
        ));
    }

    // ── Functions ─────────────────────────────────────────────────────────────

    /**
     * Creates a new management function and syncs its team and member assignments.
     *
     * Team sync runs via DB::table() on Management's own pivot table.
     * No import of Modules\Teams\Models\Team required.
     *
     * @param  StoreFunctionRequest $request
     * @return RedirectResponse
     */
    public function storeFunction(StoreFunctionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $userId    = $request->user()->id;

        $fn = ManagementFunction::create([
            'name'       => $validated['name'],
            'created_by' => $userId,
        ]);

        $this->syncFunctionTeams($fn->id, $validated['team_ids'] ?? [], $userId);

        if (! empty($validated['member_ids'])) {
            $pivot = [];
            foreach ($validated['member_ids'] as $id) {
                $pivot[(int) $id] = ['created_by' => $userId];
            }
            $fn->members()->sync($pivot);
        }

        return redirect()->route('management.index')
            ->with('success', 'Funktion „' . $fn->name . '" angelegt.');
    }

    /**
     * Updates a management function's name and re-syncs its team and member assignments.
     *
     * @param  UpdateFunctionRequest $request
     * @param  ManagementFunction    $function
     * @return RedirectResponse
     */
    public function updateFunction(UpdateFunctionRequest $request, ManagementFunction $function): RedirectResponse
    {
        $validated = $request->validated();
        $userId    = $request->user()->id;

        $function->update(['name' => $validated['name']]);

        $this->syncFunctionTeams($function->id, $validated['team_ids'] ?? [], $userId);

        $memberPivot = [];
        foreach ($validated['member_ids'] ?? [] as $id) {
            $memberPivot[(int) $id] = ['created_by' => $userId];
        }
        $function->members()->sync($memberPivot);

        return redirect()->route('management.index')
            ->with('success', 'Funktion „' . $function->name . '" gespeichert.');
    }

    /**
     * Deletes a management function (and cascades to all pivot assignments).
     *
     * @param  ManagementFunction $function
     * @return RedirectResponse
     */
    public function destroyFunction(ManagementFunction $function): RedirectResponse
    {
        $name = $function->name;
        $function->delete();

        return redirect()->route('management.index')
            ->with('success', 'Funktion „' . $name . '" gelöscht.');
    }

    // ── Tasks ─────────────────────────────────────────────────────────────────

    /**
     * Creates a new management task with optional category, priority, team, and member assignments.
     *
     * @param  StoreTaskRequest $request
     * @return RedirectResponse
     */
    public function storeTask(StoreTaskRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $userId    = $request->user()->id;

        $task = ManagementTask::create([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'priority'    => $validated['priority'] ?? 'normal',
            'created_by'  => $userId,
        ]);

        $this->syncTaskTeams($task->id, $validated['team_ids'] ?? [], $userId);

        if (! empty($validated['member_ids'])) {
            $pivot = [];
            foreach ($validated['member_ids'] as $id) {
                $pivot[(int) $id] = ['created_by' => $userId];
            }
            $task->members()->sync($pivot);
        }

        return redirect()->route('management.index')
            ->with('success', 'Aufgabe „' . $task->name . '" angelegt.');
    }

    /**
     * Updates a task's properties and re-syncs its team and member assignments.
     *
     * @param  UpdateTaskRequest $request
     * @param  ManagementTask    $task
     * @return RedirectResponse
     */
    public function updateTask(UpdateTaskRequest $request, ManagementTask $task): RedirectResponse
    {
        $validated = $request->validated();
        $userId    = $request->user()->id;

        $task->update([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'priority'    => $validated['priority'] ?? $task->priority,
        ]);

        $this->syncTaskTeams($task->id, $validated['team_ids'] ?? [], $userId);

        $memberPivot = [];
        foreach ($validated['member_ids'] ?? [] as $id) {
            $memberPivot[(int) $id] = ['created_by' => $userId];
        }
        $task->members()->sync($memberPivot);

        return redirect()->route('management.index')
            ->with('success', 'Aufgabe „' . $task->name . '" gespeichert.');
    }

    /**
     * Deletes a management task (and cascades to all pivot assignments).
     *
     * @param  ManagementTask $task
     * @return RedirectResponse
     */
    public function destroyTask(ManagementTask $task): RedirectResponse
    {
        $name = $task->name;
        $task->delete();

        return redirect()->route('management.index')
            ->with('success', 'Aufgabe „' . $name . '" gelöscht.');
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    /**
     * Synchronises the team assignments of a function via DB::table().
     *
     * Management owns the management_function_team table (module.json → tables[]).
     * No import of Modules\Teams\Models\Team is needed — team_id is a plain integer.
     *
     * @param  int        $functionId
     * @param  array<int> $teamIds
     * @param  int        $userId      The authenticated user's ID (for created_by)
     * @return void
     */
    private function syncFunctionTeams(int $functionId, array $teamIds, int $userId): void
    {
        $teamIds = array_values(array_unique(array_map('intval', $teamIds)));

        DB::table('management_function_team')->where('role_id', $functionId)->delete();

        foreach ($teamIds as $teamId) {
            DB::table('management_function_team')->insert([
                'role_id'    => $functionId,
                'team_id'    => $teamId,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Synchronises the team assignments of a task via DB::table().
     *
     * Management owns the management_task_team table (module.json → tables[]).
     *
     * @param  int        $taskId
     * @param  array<int> $teamIds
     * @param  int        $userId      The authenticated user's ID (for created_by)
     * @return void
     */
    private function syncTaskTeams(int $taskId, array $teamIds, int $userId): void
    {
        $teamIds = array_values(array_unique(array_map('intval', $teamIds)));

        DB::table('management_task_team')->where('task_id', $taskId)->delete();

        foreach ($teamIds as $teamId) {
            DB::table('management_task_team')->insert([
                'task_id'    => $taskId,
                'team_id'    => $teamId,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
