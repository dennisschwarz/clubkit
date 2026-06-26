<?php

declare(strict_types=1);

namespace Modules\Management\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Repositories\CustomFieldRepository;
use App\Services\ModuleLoader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Management\Models\ManagementFunction;
use Modules\Management\Models\ManagementTask;

class ManagementController extends Controller
{
    public function __construct(
        private readonly ModuleLoader $moduleLoader,
        private readonly CustomFieldRepository $cfRepository
    ) {}

    public function index(Request $request): View
    {
        $teamsActive   = $this->moduleLoader->isActive('teams');
        $membersActive = $this->moduleLoader->isActive('members');

        $teamFilter = $request->integer('team_id', 0) ?: null;

        // Funktionen laden
        $functionQuery = ManagementFunction::with(['teams', 'members', 'creator']);
        if ($teamFilter) {
            $functionQuery->forTeam($teamFilter);
        }
        $functions = $functionQuery->orderBy('name')->get();

        // Aufgaben laden
        $taskQuery = ManagementTask::with(['teams', 'members', 'creator']);
        if ($teamFilter) {
            $taskQuery->forTeam($teamFilter);
        }
        $tasks = $taskQuery->orderBy('name')->get();

        // Teams + Mitglieder für Filter + Modal
        $teams = $teamsActive
            ? \Modules\Teams\Models\Team::orderBy('name')->get()
            : collect();

        $members = $membersActive
            ? \Modules\Members\Models\Member::orderBy('last_name')->orderBy('first_name')->get()
            : collect();

        // JS-Daten aufbereiten (keine Arrow-Functions in @json!)
        $functionsJs = [];
        $tasksJs     = [];
        $teamsJs     = [];
        $membersJs   = [];

        foreach ($functions as $fn) {
            $functionsJs[$fn->id] = [
                'id'         => $fn->id,
                'name'       => $fn->name,
                'team_ids'   => $fn->teams->pluck('id')->values()->toArray(),
                'member_ids' => $fn->members->pluck('id')->values()->toArray(),
            ];
        }

        foreach ($tasks as $task) {
            $tasksJs[$task->id] = [
                'id'          => $task->id,
                'name'        => $task->name,
                'description' => $task->description ?? '',
                'team_ids'    => $task->teams->pluck('id')->values()->toArray(),
                'member_ids'  => $task->members->pluck('id')->values()->toArray(),
            ];
        }

        foreach ($teams as $team) {
            $teamsJs[$team->id] = ['id' => $team->id, 'name' => $team->name];
        }

        foreach ($members as $member) {
            $membersJs[$member->id] = [
                'id'   => $member->id,
                'name' => $member->last_name . ', ' . $member->first_name,
            ];
        }

        // Eigene Felder – beide Typen auf einmal in zwei DB-Abfragen laden
        $cfData = $this->cfRepository->loadForObjectTypes([
            'management_function',
            'management_task',
        ]);

        $mgmtFunctionCfDefs   = $cfData['management_function']['defs'];
        $mgmtFunctionCfValues = $cfData['management_function']['values'];
        $mgmtTaskCfDefs       = $cfData['management_task']['defs'];
        $mgmtTaskCfValues     = $cfData['management_task']['values'];

        return view('management::index', compact(
            'functions', 'tasks', 'teams', 'members',
            'functionsJs', 'tasksJs', 'teamsJs', 'membersJs',
            'teamsActive', 'membersActive', 'teamFilter',
            'mgmtFunctionCfDefs', 'mgmtFunctionCfValues',
            'mgmtTaskCfDefs',     'mgmtTaskCfValues'
        ));
    }

    // ══════════════════════════════════════════════════════════════════════
    // FUNKTIONEN
    // ══════════════════════════════════════════════════════════════════════

    public function storeFunction(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:100'],
            'team_ids'     => ['nullable', 'array'],
            'team_ids.*'   => ['integer', 'exists:teams,id'],
            'member_ids'   => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:members,id'],
        ]);

        $fn = ManagementFunction::create([
            'name'       => $validated['name'],
            'created_by' => auth()->id(),
        ]);

        if (!empty($validated['team_ids'])) {
            $pivot = [];
            foreach ($validated['team_ids'] as $id) {
                $pivot[(int) $id] = ['created_by' => auth()->id()];
            }
            $fn->teams()->sync($pivot);
        }

        if (!empty($validated['member_ids'])) {
            $pivot = [];
            foreach ($validated['member_ids'] as $id) {
                $pivot[(int) $id] = ['created_by' => auth()->id()];
            }
            $fn->members()->sync($pivot);
        }

        return redirect()->route('management.index')->with('success', 'Funktion „' . $fn->name . '" angelegt.');
    }

    public function updateFunction(Request $request, ManagementFunction $function): RedirectResponse
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:100'],
            'team_ids'     => ['nullable', 'array'],
            'team_ids.*'   => ['integer', 'exists:teams,id'],
            'member_ids'   => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:members,id'],
        ]);

        $function->update(['name' => $validated['name']]);

        $teamPivot = [];
        foreach ($validated['team_ids'] ?? [] as $id) {
            $teamPivot[(int) $id] = ['created_by' => auth()->id()];
        }
        $function->teams()->sync($teamPivot);

        $memberPivot = [];
        foreach ($validated['member_ids'] ?? [] as $id) {
            $memberPivot[(int) $id] = ['created_by' => auth()->id()];
        }
        $function->members()->sync($memberPivot);

        return redirect()->route('management.index')->with('success', 'Funktion „' . $function->name . '" gespeichert.');
    }

    public function destroyFunction(ManagementFunction $function): RedirectResponse
    {
        $name = $function->name;
        $function->delete();

        return redirect()->route('management.index')->with('success', 'Funktion „' . $name . '" gelöscht.');
    }

    // ══════════════════════════════════════════════════════════════════════
    // AUFGABEN
    // ══════════════════════════════════════════════════════════════════════

    public function storeTask(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:100'],
            'description'  => ['nullable', 'string', 'max:500'],
            'team_ids'     => ['nullable', 'array'],
            'team_ids.*'   => ['integer', 'exists:teams,id'],
            'member_ids'   => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:members,id'],
        ]);

        $task = ManagementTask::create([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'created_by'  => auth()->id(),
        ]);

        if (!empty($validated['team_ids'])) {
            $pivot = [];
            foreach ($validated['team_ids'] as $id) {
                $pivot[(int) $id] = ['created_by' => auth()->id()];
            }
            $task->teams()->sync($pivot);
        }

        if (!empty($validated['member_ids'])) {
            $pivot = [];
            foreach ($validated['member_ids'] as $id) {
                $pivot[(int) $id] = ['created_by' => auth()->id()];
            }
            $task->members()->sync($pivot);
        }

        return redirect()->route('management.index', ['tab' => 'aufgaben'])
            ->with('success', 'Aufgabe „' . $task->name . '" angelegt.');
    }

    public function updateTask(Request $request, ManagementTask $task): RedirectResponse
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:100'],
            'description'  => ['nullable', 'string', 'max:500'],
            'team_ids'     => ['nullable', 'array'],
            'team_ids.*'   => ['integer', 'exists:teams,id'],
            'member_ids'   => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:members,id'],
        ]);

        $task->update([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        $teamPivot = [];
        foreach ($validated['team_ids'] ?? [] as $id) {
            $teamPivot[(int) $id] = ['created_by' => auth()->id()];
        }
        $task->teams()->sync($teamPivot);

        $memberPivot = [];
        foreach ($validated['member_ids'] ?? [] as $id) {
            $memberPivot[(int) $id] = ['created_by' => auth()->id()];
        }
        $task->members()->sync($memberPivot);

        return redirect()->route('management.index', ['tab' => 'aufgaben'])
            ->with('success', 'Aufgabe „' . $task->name . '" gespeichert.');
    }

    public function destroyTask(ManagementTask $task): RedirectResponse
    {
        $name = $task->name;
        $task->delete();

        return redirect()->route('management.index', ['tab' => 'aufgaben'])
            ->with('success', 'Aufgabe „' . $name . '" gelöscht.');
    }
}
