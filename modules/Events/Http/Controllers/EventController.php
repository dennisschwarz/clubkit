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
use Modules\Events\Http\Requests\UpdateEventRequest;
use Modules\Events\Models\Event;
use Modules\Members\Models\Member;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Handles event CRUD and the Event Detail Page.
 *
 * Architecture notes:
 *   - Teams are optional: $teamsInstalled guard via Schema::hasTable('teams').
 *   - Management is optional: $managementInstalled guard via Schema::hasTable('event_tasks').
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
        $managementInstalled = Schema::hasTable('event_tasks');

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

        // Detect optional modules once; pass flags to view for conditional tab rendering.
        $managementInstalled = Schema::hasTable('event_tasks');
        $teamsInstalled      = Schema::hasTable('teams');

        // Task progress counters — only available when Management is installed.
        $totalTasks = 0;
        $doneTasks  = 0;
        if ($managementInstalled) {
            $totalTasks = DB::table('event_tasks')->where('event_id', $event->id)->count();
            $doneTasks  = DB::table('event_tasks')->where('event_id', $event->id)->where('completed', true)->count();
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
            'managementInstalled',
            'teamsInstalled',
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
     * Assign an existing management function to an event (member_id defaults to null).
     * Returns 409 if the function is already assigned.
     *
     * POST body: { function_id: int }
     *
     * @param  Request $request
     * @param  Event   $event
     * @return JsonResponse
     */
    public function addFunction(Request $request, Event $event): JsonResponse
    {
        if (! Schema::hasTable('event_management_function')) {
            return response()->json(['success' => false, 'message' => 'Tabelle nicht verfügbar.'], 500);
        }

        $validated  = $request->validate(['function_id' => 'required|integer']);
        $functionId = $validated['function_id'];

        if (DB::table('event_management_function')
            ->where('event_id', $event->id)
            ->where('management_function_id', $functionId)
            ->exists()) {
            return response()->json(['success' => false, 'message' => 'Funktion bereits zugewiesen.'], 409);
        }

        DB::table('event_management_function')->insert([
            'event_id'               => $event->id,
            'management_function_id' => $functionId,
            'member_id'              => null,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Assign or remove a member for a function on this event (upsert).
     *
     * PATCH body: { member_id: int|null }
     *
     * @param  Request $request
     * @param  Event   $event
     * @param  int     $functionId
     * @return JsonResponse
     */
    public function assignFunction(Request $request, Event $event, int $functionId): JsonResponse
    {
        if (! Schema::hasTable('event_management_function')) {
            return response()->json(['success' => false, 'message' => 'Tabelle nicht verfügbar.'], 500);
        }

        $validated = $request->validate(['member_id' => 'nullable|integer']);

        DB::table('event_management_function')->updateOrInsert(
            [
                'event_id'               => $event->id,
                'management_function_id' => $functionId,
            ],
            [
                'member_id'  => $validated['member_id'] ?? null,
                'updated_at' => now(),
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * Remove a management function from this event.
     *
     * @param  Event $event
     * @param  int   $functionId
     * @return JsonResponse
     */
    public function removeFunction(Event $event, int $functionId): JsonResponse
    {
        if (! Schema::hasTable('event_management_function')) {
            return response()->json(['success' => false, 'message' => 'Tabelle nicht verfügbar.'], 500);
        }

        DB::table('event_management_function')
            ->where('event_id', $event->id)
            ->where('management_function_id', $functionId)
            ->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Assign a team to this event.
     *
     * POST body: { team_id: int }
     *
     * @param  Request $request
     * @param  Event   $event
     * @return JsonResponse
     */
    public function addTeam(Request $request, Event $event): JsonResponse
    {
        if (! Schema::hasTable('event_team')) {
            return response()->json(['success' => false, 'message' => 'Tabelle nicht verfügbar.'], 500);
        }

        $validated = $request->validate(['team_id' => 'required|integer']);
        $teamId    = $validated['team_id'];

        if (DB::table('event_team')
            ->where('event_id', $event->id)
            ->where('team_id', $teamId)
            ->exists()) {
            return response()->json(['success' => false, 'message' => 'Team bereits zugewiesen.'], 409);
        }

        DB::table('event_team')->insert([
            'event_id'   => $event->id,
            'team_id'    => $teamId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Remove a team from this event.
     *
     * @param  Event $event
     * @param  int   $teamId
     * @return JsonResponse
     */
    public function removeTeam(Event $event, int $teamId): JsonResponse
    {
        if (! Schema::hasTable('event_team')) {
            return response()->json(['success' => false, 'message' => 'Tabelle nicht verfügbar.'], 500);
        }

        DB::table('event_team')
            ->where('event_id', $event->id)
            ->where('team_id', $teamId)
            ->delete();

        return response()->json(['success' => true]);
    }
}