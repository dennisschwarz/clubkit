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
 * Teams integration runs entirely through the hook system:
 *
 *   TeamsServiceProvider::boot() → registers hooks in management.function.list,
 *   management.task.list, management.*.modal.teams, management.page.scripts
 *
 * ── Sort URL format ───────────────────────────────────────────────────────────
 * Two separate lists on one page → separate parameters:
 *   ?fn_sort=name       (functions ASC)
 *   ?fn_sort=-name      (functions DESC)
 *   ?task_sort=name     (tasks ASC by name)
 *   ?task_sort=-name    (tasks DESC by name)
 *   ?task_sort=priority (tasks ASC by priority)
 *
 * Allowed values are whitelisted server-side (no QueryBuilder on this page
 * because two parallel collections without pagination are managed).
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
     * Renders the Management overview with functions, tasks and JS data bridges.
     *
     * @return View
     */
    public function index(): View
    {
        $membersActive = $this->moduleLoader->isActive('members');

        // ── Sort: functions ────────────────────────────────────────────────────
        // Separate sort parameters because two lists share the same page.
        $fnSortRaw      = request('fn_sort', 'name');
        $fnSortCol      = ltrim($fnSortRaw, '-');
        $fnSortDir      = str_starts_with($fnSortRaw, '-') ? 'desc' : 'asc';
        $allowedFnSorts = ['name'];
        if (! in_array($fnSortCol, $allowedFnSorts, true)) {
            $fnSortCol = 'name';
            $fnSortDir = 'asc';
        }

        // ── Sort: tasks ────────────────────────────────────────────────────────
        $taskSortRaw      = request('task_sort', 'name');
        $taskSortCol      = ltrim($taskSortRaw, '-');
        $taskSortDir      = str_starts_with($taskSortRaw, '-') ? 'desc' : 'asc';
        $allowedTaskSorts = ['name', 'priority'];
        if (! in_array($taskSortCol, $allowedTaskSorts, true)) {
            $taskSortCol = 'name';
            $taskSortDir = 'asc';
        }

        $functions = ManagementFunction::with(['members', 'creator'])
            ->orderBy($fnSortCol, $fnSortDir)
            ->get();

        $tasks = ManagementTask::with(['members', 'creator', 'category'])
            ->orderBy($taskSortCol, $taskSortDir)
            ->get();

        $members = $membersActive
            ? Member::orderBy('last_name')->orderBy('first_name')->get()
            : collect();

        $categories = ManagementTaskCategory::orderBy('name')->get();

        // ── JS data bridges ────────────────────────────────────────────────────
        // No fn()/arrow functions in map() — use foreach for @json() compatibility.
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
            'mgmtTaskCfDefs',     'mgmtTaskCfValues',
            'fnSortRaw', 'taskSortRaw'
        ));
    }

    // ── Functions ─────────────────────────────────────────────────────────────

    /**
     * Store a newly created management function and sync team and member assignments.
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
     * Update an existing management function and sync team and member assignments.
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
     * Delete a management function (cascades to all pivot assignments).
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
     * Store a newly created management task and sync team and member assignments.
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
            'priority'    => $validated['priority']    ?? 'normal',
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
     * Update an existing management task and sync team and member assignments.
     *
     * @param  UpdateTaskRequest  $request
     * @param  ManagementTask     $task
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
            'priority'    => $validated['priority']    ?? 'normal',
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
     * Delete a management task.
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

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Sync team assignments for a function in management_function_team.
     *
     * Uses DB::table() instead of the Eloquent Team model — no import of Modules\Teams.
     *
     * @param  int   $functionId
     * @param  array $teamIds
     * @param  int   $userId
     */
    private function syncFunctionTeams(int $functionId, array $teamIds, int $userId): void
    {
        // Pivot-Spalte heißt role_id (Legacy-Name aus Migration 000011).
        // Nicht function_id – das ist der Fehler, den dieser Fix behebt.
        DB::table('management_function_team')->where('role_id', $functionId)->delete();

        foreach ($teamIds as $teamId) {
            DB::table('management_function_team')->insert([
                'role_id'    => $functionId,
                'team_id'    => (int) $teamId,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Sync team assignments for a task in management_task_team.
     *
     * @param  int   $taskId
     * @param  array $teamIds
     * @param  int   $userId
     */
    private function syncTaskTeams(int $taskId, array $teamIds, int $userId): void
    {
        DB::table('management_task_team')->where('task_id', $taskId)->delete();

        foreach ($teamIds as $teamId) {
            DB::table('management_task_team')->insert([
                'task_id'    => $taskId,
                'team_id'    => (int) $teamId,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
