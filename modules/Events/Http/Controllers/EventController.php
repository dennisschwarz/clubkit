<?php

declare(strict_types=1);

namespace Modules\Events\Http\Controllers;

use App\Repositories\CustomFieldRepository;
use App\Services\ModuleLoader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Modules\Events\Models\Event;
use Modules\Members\Models\Member;

class EventController extends Controller
{
    public function __construct(
        private readonly CustomFieldRepository $cfRepository,
        private readonly ModuleLoader $moduleLoader
    ) {}

    public function index(): View
    {
        $managementInstalled = $this->moduleLoader->isActive('management');
        $teamsInstalled      = $this->moduleLoader->isActive('teams');

        // Assignments immer eager-loaden (event-spezifische Einmalzuweisungen)
        $events = Event::with(['assignments'])
            ->orderBy('starts_at')
            ->paginate(25)
            ->withQueryString();

        $eventIds = $events->pluck('id')->toArray();

        // Mitglieder für Assignment-Auswahl (immer nötig für Sektion 3)
        $members = Member::select('id', 'first_name', 'last_name')
            ->orderBy('last_name')
            ->get();

        // Teams (optional)
        $teams = collect();
        if ($teamsInstalled) {
            $teams = DB::table('teams')->select('id', 'name', 'color')->orderBy('name')->get();
        }

        // Vereinsfunktionen + ihre Mitglieder (wenn Management aktiv)
        $mgmtFunctions        = collect();
        $eventMgmtFunctionIds = [];

        if ($managementInstalled) {
            $mgmtFunctions = \Modules\Management\Models\ManagementFunction::with('members')
                ->orderBy('name')
                ->get();

            if (!empty($eventIds) && Schema::hasTable('event_management_function')) {
                $rows = DB::table('event_management_function')
                    ->whereIn('event_id', $eventIds)
                    ->get();
                foreach ($rows as $row) {
                    $eventMgmtFunctionIds[$row->event_id][] = $row->management_function_id;
                }
            }
        }

        // Aufgaben (wenn Management aktiv)
        // WICHTIG: Tasks-Liste immer laden (für Modal-Checkboxen),
        //          Pivot-Abfrage nur wenn Events auf der aktuellen Seite vorhanden sind.
        $tasks        = collect();
        $eventTaskIds = [];

        if ($managementInstalled) {
            if (Schema::hasTable('management_tasks')) {
                $tasks = DB::table('management_tasks')
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get();
            }
            if (!empty($eventIds) && Schema::hasTable('event_task')) {
                $rows = DB::table('event_task')->whereIn('event_id', $eventIds)->get();
                foreach ($rows as $row) {
                    $eventTaskIds[$row->event_id][] = $row->task_id;
                }
            }
        }

        // Team-Pivot nur für aktuelle Seite
        $eventTeamIds = [];
        if ($teamsInstalled && Schema::hasTable('event_team') && !empty($eventIds)) {
            $rows = DB::table('event_team')->whereIn('event_id', $eventIds)->get();
            foreach ($rows as $row) {
                $eventTeamIds[$row->event_id][] = $row->team_id;
            }
        }

        // ── Data Bridge ────────────────────────────────────────────────────────
        // Kein fn() / Arrow-Functions in @json()

        $eventsJs = [];
        foreach ($events as $ev) {
            $eventsJs[$ev->id] = [
                'id'                      => $ev->id,
                'title'                   => $ev->title,
                'description'             => $ev->description,
                'starts_at'               => $ev->starts_at?->format('Y-m-d H:i'),
                'ends_at'                 => $ev->ends_at?->format('Y-m-d H:i'),
                'location'                => $ev->location,
                'notes'                   => $ev->notes,
                'assignments'             => $this->buildAssignmentsJs($ev),
                'management_function_ids' => $eventMgmtFunctionIds[$ev->id] ?? [],
                'team_ids'                => $eventTeamIds[$ev->id] ?? [],
                'task_ids'                => $eventTaskIds[$ev->id] ?? [],
            ];
        }

        $membersJs = [];
        foreach ($members as $m) {
            $membersJs[$m->id] = ['id' => $m->id, 'name' => $m->last_name . ', ' . $m->first_name];
        }

        $teamsJs = [];
        foreach ($teams as $t) {
            $teamsJs[$t->id] = ['id' => $t->id, 'name' => $t->name, 'color' => $t->color ?? null];
        }

        $tasksJs = [];
        foreach ($tasks as $t) {
            $tasksJs[$t->id] = ['id' => $t->id, 'name' => $t->name];
        }

        $mgmtFunctionsJs = [];
        foreach ($mgmtFunctions as $fn) {
            $memberNames = [];
            $memberIds   = [];
            foreach ($fn->members as $m) {
                $memberNames[] = $m->last_name . ', ' . $m->first_name;
                $memberIds[]   = $m->id;
            }
            $mgmtFunctionsJs[$fn->id] = [
                'id'           => $fn->id,
                'name'         => $fn->name,
                'member_names' => $memberNames,
                'member_ids'   => $memberIds,
            ];
        }

        // Eigene Felder
        $cf            = $this->cfRepository->loadForObjectType('event');
        $eventCfDefs   = $cf['defs'];
        $eventCfValues = $cf['values'];

        return view('events::index', compact(
            'events', 'members', 'teams', 'tasks', 'mgmtFunctions',
            'teamsInstalled', 'managementInstalled',
            'eventTeamIds', 'eventTaskIds', 'eventMgmtFunctionIds',
            'eventsJs', 'membersJs', 'teamsJs', 'tasksJs', 'mgmtFunctionsJs',
            'eventCfDefs', 'eventCfValues'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated               = $this->validateEvent($request);
        $validated['created_by'] = auth()->id();

        $event = Event::create($validated);

        // Immer: einmalige Assignments (event-spezifisch)
        $this->syncAssignments($event, $request);

        // Optional: Vereinsfunktionen + Aufgaben (wenn Management aktiv)
        if ($this->moduleLoader->isActive('management')) {
            $this->syncManagementFunctions($event, $request);
            $this->syncTasks($event, $request);
        }

        $this->syncTeams($event, $request);

        return redirect()->route('events.index')->with('success', 'Termin angelegt.');
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $validated = $this->validateEvent($request);
        $event->update($validated);

        // Immer: einmalige Assignments
        $this->syncAssignments($event, $request);

        // Optional: Vereinsfunktionen + Aufgaben
        if ($this->moduleLoader->isActive('management')) {
            $this->syncManagementFunctions($event, $request);
            $this->syncTasks($event, $request);
        }

        $this->syncTeams($event, $request);

        return redirect()->route('events.index')->with('success', 'Termin aktualisiert.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $event->delete();

        return redirect()->route('events.index')->with('success', 'Termin gelöscht.');
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    private function validateEvent(Request $request): array
    {
        return $request->validate([
            'title'       => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'starts_at'   => ['required', 'date'],
            'ends_at'     => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location'    => ['nullable', 'string', 'max:150'],
            'notes'       => ['nullable', 'string'],
        ]);
    }

    /**
     * Einmalige Sonder-Assignments (immer, unabhängig vom Management-Modul).
     * Speichert in event_assignments (person + description).
     */
    private function syncAssignments(Event $event, Request $request): void
    {
        $syncData = [];
        foreach ($request->input('assignments', []) as $assignment) {
            if (!empty($assignment['member_id'])) {
                $syncData[(int) $assignment['member_id']] = [
                    'description' => $assignment['description'] ?? null,
                ];
            }
        }
        $event->assignments()->sync($syncData);
    }

    /**
     * Vereinsfunktionen am Termin zuweisen (nur wenn Management-Modul aktiv).
     * Members der Funktionen sind implizit am Termin beteiligt.
     * Keine neuen Funktionen anlegen — das geschieht im Management-Modul.
     */
    private function syncManagementFunctions(Event $event, Request $request): void
    {
        if (!Schema::hasTable('event_management_function')) {
            return;
        }

        $functionIds = array_values(array_filter(
            array_map('intval', $request->input('management_function_ids', []))
        ));

        $event->managementFunctions()->sync($functionIds);
    }

    private function syncTeams(Event $event, Request $request): void
    {
        if (!Schema::hasTable('event_team')) {
            return;
        }

        $teamIds = array_values(array_filter(
            array_map('intval', $request->input('team_ids', []))
        ));

        $event->teams()->sync($teamIds);
    }

    private function syncTasks(Event $event, Request $request): void
    {
        if (!Schema::hasTable('event_task')) {
            return;
        }

        $taskIds = array_values(array_filter(
            array_map('intval', $request->input('task_ids', []))
        ));

        $event->tasks()->sync($taskIds);
    }

    private function buildAssignmentsJs(Event $event): array
    {
        $result = [];
        foreach ($event->assignments as $assignment) {
            $result[] = [
                'member_id'   => $assignment->id,
                'description' => $assignment->pivot->description ?? '',
            ];
        }
        return $result;
    }
}
