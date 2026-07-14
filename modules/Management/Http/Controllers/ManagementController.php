<?php

declare(strict_types=1);

namespace Modules\Management\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Repositories\CustomFieldRepository;
use App\Services\ModuleLoader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                'id'          => $fn->id,
                'name'        => $fn->name,
                'description' => $fn->description ?? '',
                'member_ids'  => $fn->members->pluck('id')->values()->toArray(),
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

        $membersJs = [];
        foreach ($members as $m) {
            $membersJs[$m->id] = ['id' => $m->id, 'name' => $m->last_name . ', ' . $m->first_name];
        }

        $chevronSvg = self::chevronSvg();

        return view('management::index', compact(
            'functions', 'tasks', 'members', 'categories',
            'functionsJs', 'tasksJs', 'membersJs', 'categoriesJs',
            'membersActive',
            'mgmtFunctionCfDefs', 'mgmtFunctionCfValues',
            'mgmtTaskCfDefs',     'mgmtTaskCfValues',
            'fnSortRaw', 'taskSortRaw',
            'chevronSvg'
        ));
    }

    // ── Functions ─────────────────────────────────────────────────────────────

    /**
     * Store a newly created management function and sync team and member assignments.
     * When called via AJAX (expectsJson), returns JSON instead of a redirect.
     *
     * @param  StoreFunctionRequest $request
     * @return JsonResponse|RedirectResponse
     */
    public function storeFunction(StoreFunctionRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();
        $userId    = $request->user()->id;

        $fn = ManagementFunction::create([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'created_by'  => $userId,
        ]);

        // team_id comes from the hidden field set by mgmtModalOpen() when clicking
        // a section "+" button; falls back to team_ids[] from the form (legacy/non-JS).
        $teamId  = $request->integer('team_id') ?: null;
        $teamIds = $teamId ? [$teamId] : ($validated['team_ids'] ?? []);
        $this->syncFunctionTeams($fn->id, $teamIds, $userId);

        if (! empty($validated['member_ids'])) {
            $pivot = [];
            foreach ($validated['member_ids'] as $id) {
                $pivot[(int) $id] = ['created_by' => $userId];
            }
            $fn->members()->sync($pivot);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'id' => $fn->id, 'name' => $fn->name], 201);
        }

        return redirect()->route('management.index')
            ->with('success', __('management.flash.function_created', ['name' => $fn->name]));
    }

    /**
     * Update an existing management function and sync team and member assignments.
     *
     * @param  UpdateFunctionRequest $request
     * @param  ManagementFunction    $function
     * @return RedirectResponse
     */
    public function updateFunction(UpdateFunctionRequest $request, ManagementFunction $function): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();
        $userId    = $request->user()->id;

        $function->update([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);
        $this->syncFunctionTeams($function->id, $validated['team_ids'] ?? [], $userId);

        // Only sync members when explicitly sent — the assign modal manages them separately.
        if ($request->has('member_ids')) {
            $pivot = [];
            foreach ($validated['member_ids'] ?? [] as $id) {
                $pivot[(int) $id] = ['created_by' => $userId];
            }
            $function->members()->sync($pivot);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'id' => $function->id, 'name' => $function->name]);
        }
        return redirect()->route('management.index')
            ->with('success', __('management.flash.function_updated', ['name' => $function->name]));
    }

    public function destroyFunction(ManagementFunction $function): JsonResponse|RedirectResponse
    {
        $name = $function->name;
        $function->delete();

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'name' => $name]);
        }
        return redirect()->route('management.index')
            ->with('success', __('management.flash.function_deleted', ['name' => $name]));
    }

    // ── Tasks ─────────────────────────────────────────────────────────────────

    /**
     * Store a newly created management task and sync team and member assignments.
     * When called via AJAX (expectsJson), returns JSON with the new task id and name.
     *
     * @param  StoreTaskRequest $request
     * @return JsonResponse|RedirectResponse
     */
    public function storeTask(StoreTaskRequest $request): JsonResponse|RedirectResponse
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

        // team_id comes from the hidden field set by mgmtModalOpen() when clicking
        // a section "+" button; falls back to team_ids[] from the form (legacy/non-JS).
        $teamId  = $request->integer('team_id') ?: null;
        $teamIds = $teamId ? [$teamId] : ($validated['team_ids'] ?? []);
        $this->syncTaskTeams($task->id, $teamIds, $userId);

        if (! empty($validated['member_ids'])) {
            $pivot = [];
            foreach ($validated['member_ids'] as $id) {
                $pivot[(int) $id] = ['created_by' => $userId];
            }
            $task->members()->sync($pivot);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'id' => $task->id, 'name' => $task->name], 201);
        }

        return redirect()->route('management.index')
            ->with('success', __('management.flash.task_created', ['name' => $task->name]));
    }

    /**
     * Update an existing management task and sync team and member assignments.
     *
     * @param  UpdateTaskRequest  $request
     * @param  ManagementTask     $task
     * @return RedirectResponse
     */
    public function updateTask(UpdateTaskRequest $request, ManagementTask $task): JsonResponse|RedirectResponse
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

        // Only sync members when explicitly sent — the assign modal manages them separately.
        if ($request->has('member_ids')) {
            $pivot = [];
            foreach ($validated['member_ids'] ?? [] as $id) {
                $pivot[(int) $id] = ['created_by' => $userId];
            }
            $task->members()->sync($pivot);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'id' => $task->id, 'name' => $task->name]);
        }
        return redirect()->route('management.index')
            ->with('success', __('management.flash.task_updated', ['name' => $task->name]));
    }

    public function destroyTask(ManagementTask $task): JsonResponse|RedirectResponse
    {
        $name = $task->name;
        $task->delete();

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'name' => $name]);
        }
        return redirect()->route('management.index')
            ->with('success', __('management.flash.task_deleted', ['name' => $name]));
    }

    // ── HTML fragments (AJAX tab refresh) ────────────────────────────────────

    /**
     * Returns the rendered function list section for AJAX DOM-swap.
     * Muss dieselben Daten mitgeben wie index(), damit View Composer und
     * Hooks korrekt arbeiten (z. B. TeamsServiceProvider).
     */
    public function functionsFragment(): \Illuminate\Http\Response
    {
        $fnSortRaw = request('fn_sort', 'name');
        $fnSortCol = ltrim($fnSortRaw, '-');
        $fnSortDir = str_starts_with($fnSortRaw, '-') ? 'desc' : 'asc';
        if (! in_array($fnSortCol, ['name'], true)) {
            $fnSortCol = 'name';
            $fnSortDir = 'asc';
        }

        $functions  = ManagementFunction::with(['members', 'creator'])
            ->orderBy($fnSortCol, $fnSortDir)
            ->get();
        $chevronSvg = self::chevronSvg();

        return response(
            view('management::_functions-fragment', compact('functions', 'fnSortRaw', 'chevronSvg'))->render(),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    /**
     * Returns the rendered task list section for AJAX DOM-swap.
     * Muss dieselben Daten mitgeben wie index(), damit View Composer und
     * Hooks korrekt arbeiten (z. B. TeamsServiceProvider lädt ->teams).
     */
    public function tasksFragment(): \Illuminate\Http\Response
    {
        $taskSortRaw = request('task_sort', 'name');
        $taskSortCol = ltrim($taskSortRaw, '-');
        $taskSortDir = str_starts_with($taskSortRaw, '-') ? 'desc' : 'asc';
        if (! in_array($taskSortCol, ['name', 'priority'], true)) {
            $taskSortCol = 'name';
            $taskSortDir = 'asc';
        }

        $tasks      = ManagementTask::with(['members', 'creator', 'category'])
            ->orderBy($taskSortCol, $taskSortDir)
            ->get();
        $categories = ManagementTaskCategory::orderBy('name')->get();
        $chevronSvg = self::chevronSvg();

        return response(
            view('management::_tasks-fragment', compact('tasks', 'categories', 'taskSortRaw', 'chevronSvg'))->render(),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    // ── Drag & drop: move function/task to a different team section ──────────────

    /**
     * Moves a ManagementFunction into a team section (or "Allgemein").
     * Replaces all current team assignments with the given team_id (or none).
     */
    public function moveFunction(Request $request, ManagementFunction $function): JsonResponse
    {
        $teamId = $request->integer('team_id') ?: null;
        $function->teams()->sync($teamId ? [$teamId] : []);

        return response()->json(['success' => true]);
    }

    /**
     * Moves a ManagementTask into a team section (or "Allgemein").
     * Replaces all current team assignments with the given team_id (or none).
     */
    public function moveTask(Request $request, ManagementTask $task): JsonResponse
    {
        $teamId = $request->integer('team_id') ?: null;
        $task->teams()->sync($teamId ? [$teamId] : []);

        return response()->json(['success' => true]);
    }

    /**
     * Returns the chevron SVG icon as an HTML string.
     * Shared between index() and the fragment endpoints so the string
     * is forwarded via @ckHook into the Teams hook views.
     */
    private static function chevronSvg(): string
    {
        return '<svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">'
             . '<path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293'
             . 'a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>'
             . '</svg>';
    }

    // ── Member assignment (individual add/remove via mgmtAssignModal) ─────────

    public function addFunctionMember(Request $request, ManagementFunction $function): JsonResponse
    {
        $memberId = (int) $request->input('member_id');
        if (! $memberId) {
            return response()->json(['success' => false, 'message' => 'member_id required'], 422);
        }
        if ($function->members()->where('member_id', $memberId)->exists()) {
            return response()->json(['success' => false, 'error' => 'already_assigned'], 422);
        }
        $function->members()->attach($memberId, ['created_by' => $request->user()->id]);
        return response()->json(['success' => true, 'member_id' => $memberId]);
    }

    public function removeFunctionMember(ManagementFunction $function, int $memberId): JsonResponse
    {
        $function->members()->detach($memberId);
        return response()->json(['success' => true, 'member_id' => $memberId]);
    }

    public function addTaskMember(Request $request, ManagementTask $task): JsonResponse
    {
        $memberId = (int) $request->input('member_id');
        if (! $memberId) {
            return response()->json(['success' => false, 'message' => 'member_id required'], 422);
        }
        if ($task->members()->where('member_id', $memberId)->exists()) {
            return response()->json(['success' => false, 'error' => 'already_assigned'], 422);
        }
        $task->members()->attach($memberId, ['created_by' => $request->user()->id]);
        return response()->json(['success' => true, 'member_id' => $memberId]);
    }

    public function removeTaskMember(ManagementTask $task, int $memberId): JsonResponse
    {
        $task->members()->detach($memberId);
        return response()->json(['success' => true, 'member_id' => $memberId]);
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
        // Pivot column is named role_id (legacy name from migration 000011), not function_id.
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
