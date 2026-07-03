<?php

declare(strict_types=1);

namespace Modules\Events\Http\Controllers;

use App\Repositories\CustomFieldRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Modules\Events\Http\Requests\StoreEventRequest;
use Modules\Events\Http\Requests\StoreEventTaskRequest;
use Modules\Events\Http\Requests\UpdateEventRequest;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventTaskMember;
use Modules\Members\Models\Member;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Handles event CRUD and the Event Detail Page.
 *
 * Architecture notes:
 *   - Teams are optional: $teamsInstalled guard via Schema::hasTable('teams').
 *   - Management is optional: $managementInstalled guard via Schema::hasTable('management_tasks').
 *   - No direct Eloquent imports from optional modules; DB::table() used for cross-module queries.
 *
 * Allowed sort fields (via ?sort=... | ?sort=-...):
 *   starts_at (default DESC), ends_at, title, location
 *
 * allowedSorts() accepts variadic args — NO array wrapper.
 */
class EventController extends Controller
{
    public function __construct(
        private readonly CustomFieldRepository $cfRepository
    ) {}

    /**
     * Display the paginated event list.
     * Passes optional-module flags and team list for view hooks.
     *
     * @return View
     */
    public function index(): View
    {
        $events = QueryBuilder::for(Event::class)
            ->with('creator')
            ->allowedSorts('starts_at', 'ends_at', 'title', 'location')
            ->defaultSort('-starts_at')
            ->paginate(25)
            ->withQueryString();

        $teamsInstalled      = Schema::hasTable('teams');
        $managementInstalled = Schema::hasTable('management_tasks');

        // DB::table() instead of Team::all() — no cross-module Eloquent import
        $teams = $teamsInstalled
            ? DB::table('teams')->orderBy('name')->get()
            : collect();

        return view('events::index', compact('events', 'teamsInstalled', 'managementInstalled', 'teams'));
    }

    /**
     * Store a newly created event.
     *
     * @param  StoreEventRequest $request
     * @return RedirectResponse
     */
    public function store(StoreEventRequest $request): RedirectResponse
    {
        $event = Event::create(array_merge(
            $request->validated(),
            ['created_by' => $request->user()->id]
        ));

        return redirect()->route('events.show', $event);
    }

    /**
     * Display the event detail page with member JS data bridge.
     *
     * @param  Event $event
     * @return View
     */
    public function show(Event $event): View
    {
        $event->load('creator');

        // Named $allMembersJs to match the CK_EventDetail.members key in show.blade.php.
        $allMembersJs = [];
        foreach (Member::active()->orderBy('last_name')->get() as $m) {
            $allMembersJs[$m->id] = ['id' => $m->id, 'name' => $m->full_name];
        }

        // Task progress counters — guarded: event_task only exists when Management is installed.
        $totalTasks = 0;
        $doneTasks  = 0;
        if (Schema::hasTable('event_task')) {
            $totalTasks = DB::table('event_task')->where('event_id', $event->id)->count();
            $doneTasks  = DB::table('event_task')->where('event_id', $event->id)->where('completed', true)->count();
        }

        // Custom fields: extract defs/values into named variables expected by the view.
        $cf            = $this->cfRepository->loadForObjectType('event');
        $eventCfDefs   = $cf['defs'];
        $eventCfValues = $cf['values'];

        return view('events::show', compact(
            'event',
            'allMembersJs',
            'totalTasks',
            'doneTasks',
            'eventCfDefs',
            'eventCfValues',
        ));
    }

    /**
     * Update an existing event.
     *
     * @param  UpdateEventRequest $request
     * @param  Event              $event
     * @return RedirectResponse
     */
    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $event->update($request->validated());

        return redirect()->route('events.show', $event)->with('success', 'Termin gespeichert.');
    }

    /**
     * Delete an event.
     *
     * @param  Event $event
     * @return RedirectResponse
     */
    public function destroy(Event $event): RedirectResponse
    {
        $event->delete();

        return redirect()->route('events.index')->with('success', 'Termin gelöscht.');
    }

    /**
     * Toggle the completed state of a management task assigned to an event.
     * Used by the AJAX progress bar in events-detail.js.
     *
     * @param  int $eventId
     * @param  int $taskId
     * @return JsonResponse
     */
    public function completeTask(int $eventId, int $taskId): JsonResponse
    {
        $pivot = DB::table('event_task')
            ->where('event_id', $eventId)
            ->where('task_id', $taskId)
            ->first();

        if (! $pivot) {
            return response()->json(['error' => 'Not found'], 404);
        }

        DB::table('event_task')
            ->where('event_id', $eventId)
            ->where('task_id', $taskId)
            ->update(['completed' => ! $pivot->completed]);

        return response()->json(['completed' => ! $pivot->completed]);
    }

    /**
     * Assign a management task to an event.
     * Returns 409 if the task is already assigned.
     *
     * @param  StoreEventTaskRequest $request
     * @param  Event                 $event
     * @return JsonResponse
     */
    public function addTask(StoreEventTaskRequest $request, Event $event): JsonResponse
    {
        $taskId = $request->validated()['task_id'];

        if (DB::table('event_task')->where('event_id', $event->id)->where('task_id', $taskId)->exists()) {
            return response()->json(['error' => 'already_assigned'], 409);
        }

        DB::table('event_task')->insert([
            'event_id'   => $event->id,
            'task_id'    => $taskId,
            'completed'  => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Remove a management task from an event.
     *
     * @param  Event $event
     * @param  int   $taskId
     * @return JsonResponse
     */
    public function removeTask(Event $event, int $taskId): JsonResponse
    {
        DB::table('event_task')
            ->where('event_id', $event->id)
            ->where('task_id', $taskId)
            ->delete();

        return response()->json(['success' => true]);
    }
}